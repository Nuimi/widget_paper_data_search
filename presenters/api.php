<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$debugMode = isset($_GET['debug']) && $_GET['debug'] === '1';
$settingsFetchDebug = [];

function sendJsonAndExit(array $payload, int $statusCode = 200)
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) use ($debugMode) {
    sendJsonAndExit([
        'error' => 'API exception',
        'message' => $e->getMessage(),
        'file' => $debugMode ? $e->getFile() : null,
        'line' => $debugMode ? $e->getLine() : null,
        'type' => get_class($e),
    ], 500);
});

register_shutdown_function(function () use ($debugMode) {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    sendJsonAndExit([
        'error' => 'API fatal error',
        'message' => $error['message'],
        'file' => $debugMode ? $error['file'] : null,
        'line' => $debugMode ? $error['line'] : null,
        'type' => $error['type'],
    ], 500);
});

if (isset($_GET['health']) && $_GET['health'] === '1') {
    sendJsonAndExit([
        'ok' => true,
        'phpVersion' => PHP_VERSION,
        'file' => __FILE__,
        'dir' => __DIR__,
        'configExists' => file_exists(dirname(__DIR__) . '/config/config.php'),
        'curlAvailable' => function_exists('curl_init'),
        'allowUrlFopen' => (bool) ini_get('allow_url_fopen'),
    ]);
}

$configPath = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configPath)) {
    sendJsonAndExit([
        'error' => 'Missing config file',
        'configPath' => $debugMode ? $configPath : null,
    ], 500);
}

require_once $configPath;

function getDoi($q)
{
    if (preg_match('/\b10\.\d{4,9}\/[-._;()\/:A-Z0-9]+/i', $q, $m)) {
        return $m[0];
    }

    return false;
}

function askOpenAlex($q)
{
    if ($doi = getDoi($q)) {
        $openalexUrl = 'https://api.openalex.org/works/https://doi.org/' . urlencode($doi);
    } else {
        $openalexUrl = 'https://api.openalex.org/works?search=' . urlencode($q) . '&per-page=1';
    }

    return requestPublicJson($openalexUrl);
}

function requestData($url, $apiKey)
{
    $ch = curl_init($url);
    $verbose = fopen('php://temp', 'w+');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLINFO_HEADER_OUT => true,
        CURLOPT_VERBOSE => true,
        CURLOPT_STDERR => $verbose,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-ApiKey: ' . trim($apiKey),
        ],
    ]);

    $raw = curl_exec($ch);

    if ($raw === false) {
        $errNo = curl_errno($ch);
        $errMsg = curl_error($ch);
        $sentHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);

        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        fclose($verbose);

        curl_close($ch);

        return [
            'error' => 'cURL error',
            'errno' => $errNo,
            'message' => $errMsg,
            'url' => $url,
            'sentHeaders' => $sentHeaders ?: null,
            'verbose' => substr($verboseLog ?: '', 0, 2000),
        ];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $sentHeaders = curl_getinfo($ch, CURLINFO_HEADER_OUT);

    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'error' => 'Clarivate API error',
            'status' => $httpCode,
            'contentType' => $contentType,
            'url' => $url,
            'sentHeaders' => $sentHeaders,
            'verbose' => substr($verboseLog ?: '', 0, 2000),
            'headersPreview' => substr($headers, 0, 500),
            'bodyPreview' => substr($body, 0, 500),
        ];
    }

    if (!is_string($contentType) || stripos($contentType, 'application/json') === false) {
        return [
            'error' => 'Response is not JSON',
            'status' => $httpCode,
            'contentType' => $contentType,
            'url' => $url,
            'sentHeaders' => $sentHeaders,
            'verbose' => substr($verboseLog ?: '', 0, 2000),
            'headersPreview' => substr($headers, 0, 500),
            'bodyPreview' => substr($body, 0, 500),
        ];
    }

    $json = json_decode($body, true);

    if (!is_array($json)) {
        return [
            'error' => 'Invalid JSON from Clarivate',
            'status' => $httpCode,
            'contentType' => $contentType,
            'url' => $url,
            'sentHeaders' => $sentHeaders,
            'verbose' => substr($verboseLog ?: '', 0, 2000),
            'headersPreview' => substr($headers, 0, 500),
            'bodyPreview' => substr($body, 0, 500),
        ];
    }

    return $json;
}

function tryDecodeJsonPayload($raw)
{
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $jsonStart = strpos($raw, '{');
    $jsonEnd = strrpos($raw, '}');
    if ($jsonStart === false || $jsonEnd === false || $jsonEnd < $jsonStart) {
        return null;
    }

    $slice = substr($raw, $jsonStart, $jsonEnd - $jsonStart + 1);
    $decoded = json_decode($slice, true);
    return is_array($decoded) ? $decoded : null;
}

function requestJson($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT => 'Q-Quartile-Widget/0.5.1',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'ok' => $raw !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'contentType' => $contentType,
        'curlError' => $curlError ?: null,
        'rawPreview' => is_string($raw) ? substr($raw, 0, 500) : null,
        'json' => is_string($raw) ? tryDecodeJsonPayload($raw) : null,
    ];
}

function requestPublicJson($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Q-Quartile-Widget/0.5.1',
        ],
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
        curl_close($ch);
        return false;
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        return false;
    }

    if (!is_string($contentType) || stripos($contentType, 'application/json') === false) {
        return false;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : false;
}

function fetchUserSettingsFromBackend(?string $token): ?array
{
    global $settingsFetchDebug;

    if (empty($token)) {
        $settingsFetchDebug = [
            'skipped' => true,
            'reason' => 'missing-token',
        ];
        return [];
    }

    $url = 'https://imitweby.uhk.cz/widget/ajax/userSettings?' . http_build_query([
        'token' => $token,
    ]);

    $response = requestJson($url);
    $settingsFetchDebug = [
        'url' => $url,
        'ok' => $response['ok'],
        'status' => $response['status'],
        'contentType' => $response['contentType'],
        'curlError' => $response['curlError'],
        'rawPreview' => $response['rawPreview'],
        'jsonKeys' => is_array($response['json']) ? array_keys($response['json']) : null,
    ];

    if (!$response['ok'] || !is_array($response['json'])) {
        return null;
    }

    return is_array($response['json']['settings'] ?? null) ? $response['json']['settings'] : [];
}

function fetchUserSettingsFromDatabase(?string $token): ?array
{
    global $settingsFetchDebug;

    if (empty($token)) {
        return [];
    }

    $connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    if (!$connection) {
        $settingsFetchDebug['dbConnectError'] = mysqli_connect_error();
        return null;
    }

    mysqli_set_charset($connection, DB_CHARSET);

    $sql = 'SELECT s.settings
        FROM tSettings s
        LEFT JOIN tUser u ON s.FK_userID = u.id
        WHERE u.token = ?
        LIMIT 1';

    $statement = mysqli_prepare($connection, $sql);
    if (!$statement) {
        $settingsFetchDebug['dbPrepareError'] = mysqli_error($connection);
        mysqli_close($connection);
        return null;
    }

    mysqli_stmt_bind_param($statement, 's', $token);
    mysqli_stmt_execute($statement);
    mysqli_stmt_bind_result($statement, $rawSettings);
    $fetched = mysqli_stmt_fetch($statement);

    mysqli_stmt_close($statement);
    mysqli_close($connection);

    if (!$fetched || empty($rawSettings)) {
        return [];
    }

    $decoded = json_decode($rawSettings, true);
    return is_array($decoded) ? $decoded : [];
}

function fetchUserSettings(?string $token): array
{
    global $settingsFetchDebug;

    $settings = fetchUserSettingsFromBackend($token);
    if (is_array($settings)) {
        $settingsFetchDebug['source'] = 'ajax';
        return $settings;
    }

    $settings = fetchUserSettingsFromDatabase($token);
    if (is_array($settings)) {
        $settingsFetchDebug['source'] = 'db-fallback';
        return $settings;
    }

    $settingsFetchDebug['source'] = 'none';
    return [];
}

function resolveSelectedItems(array $settings, string $group, array $map): array
{
    if (!array_key_exists($group, $settings)) {
        return [];
    }

    $groupSettings = $settings[$group];
    if (!is_array($groupSettings)) {
        return array_values($map);
    }

    $selected = [];
    foreach ($map as $settingKey => $targetKey) {
        if (!empty($groupSettings[$settingKey])) {
            $selected[] = $targetKey;
        }
    }

    return $selected;
}

function buildDisplaySettings(array $settings): array
{
    $displaySettings = [
        'hasCustomSettings' => !empty($settings),
        'simpleRows' => [],
        'rankColumns' => [
            'jifRanks' => [],
            'articleInfluenceRanks' => [],
        ],
    ];

    if (empty($settings)) {
        return $displaySettings;
    }

    $simpleFieldGroups = [
        'impactMetrics' => [
            'jif' => 'jif',
            'jif5Years' => 'jif5Years',
            'jifWithoutSelfCitations' => 'jifWithoutSelfCitations',
            'jci' => 'jci',
            'immediacyIndex' => 'immediacyIndex',
            'totalCites' => 'totalCites',
        ],
        'influenceMetrics' => [
            'articleInfluence' => 'articleInfluence',
            'eigenFactorScore' => 'eigenFactorScore',
            'eigenFactorNormalized' => 'eigenFactorNormalized',
        ],
        'sourceMetrics' => [
            'jifPercentile' => 'jifPercentile',
            'citableItemsTotal' => 'citableItemsTotal',
            'citableItemsArticlesPercentage' => 'citableItemsArticlesPercentage',
            'halfLifeCited' => 'halfLifeCited',
            'halfLifeCiting' => 'halfLifeCiting',
        ],
    ];

    foreach ($simpleFieldGroups as $group => $map) {
        foreach (resolveSelectedItems($settings, $group, $map) as $field) {
            $displaySettings['simpleRows'][$field] = true;
        }
    }

    if (!empty($settings['jifPercentile'])) {
        $displaySettings['simpleRows']['jifPercentile'] = true;
    }

    $displaySettings['rankColumns']['jifRanks'] = resolveSelectedItems($settings, 'jif', [
        'jifC' => 'category',
        'jifE' => 'edition',
        'jifQ' => 'quartile',
        'jifR' => 'rank',
    ]);

    if (array_key_exists('ainf', $settings)) {
        $ainfSettings = $settings['ainf'];
        if (!is_array($ainfSettings)) {
            $displaySettings['rankColumns']['articleInfluenceRanks'] = ['category', 'edition', 'quartile', 'rank'];
        } else {
            if (!empty($ainfSettings['ainfC']) || !empty($ainfSettings['ainC'])) {
                $displaySettings['rankColumns']['articleInfluenceRanks'][] = 'category';
            }
            if (!empty($ainfSettings['ainfE']) || !empty($ainfSettings['ainE'])) {
                $displaySettings['rankColumns']['articleInfluenceRanks'][] = 'edition';
            }
            if (!empty($ainfSettings['ainQ'])) {
                $displaySettings['rankColumns']['articleInfluenceRanks'][] = 'quartile';
            }
            if (!empty($ainfSettings['ainR'])) {
                $displaySettings['rankColumns']['articleInfluenceRanks'][] = 'rank';
            }
        }
    }

    return $displaySettings;
}

function filterRankRows(array $rows, array $columns): array
{
    if (empty($columns)) {
        return [];
    }

    $filteredRows = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $filteredRow = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                $filteredRow[$column] = $row[$column];
            }
        }
        $filteredRows[] = $filteredRow;
    }

    return $filteredRows;
}

function applyDisplaySettings(array $response, array $displaySettings): array
{
    if (!$displaySettings['hasCustomSettings']) {
        $response['displaySettings'] = $displaySettings;
        return $response;
    }

    $metricFields = [
        'jif',
        'jif5Years',
        'jifWithoutSelfCitations',
        'jci',
        'immediacyIndex',
        'totalCites',
        'articleInfluence',
        'eigenFactorScore',
        'eigenFactorNormalized',
        'jifPercentile',
        'citableItemsTotal',
        'citableItemsArticlesPercentage',
        'halfLifeCited',
        'halfLifeCiting',
    ];

    foreach ($metricFields as $field) {
        if (empty($displaySettings['simpleRows'][$field])) {
            unset($response[$field]);
        }
    }

    $response['jifRanks'] = filterRankRows($response['jifRanks'] ?? [], $displaySettings['rankColumns']['jifRanks'] ?? []);
    $response['articleInfluenceRanks'] = filterRankRows($response['articleInfluenceRanks'] ?? [], $displaySettings['rankColumns']['articleInfluenceRanks'] ?? []);

    unset($response['metrics'], $response['raw']);

    $response['displaySettings'] = $displaySettings;
    return $response;
}

function searchWoSAPIAll($issn)
{
    $usrQuery = sprintf('IS=%s', $issn);
    $url = 'https://wos-api.clarivate.com/api/wos/?' . http_build_query([
        'databaseId' => 'WOS',
        'usrQuery' => $usrQuery,
        'count' => 10,
        'firstRecord' => 1,
        'sortField' => 'LD+D',
    ]);

    return requestData($url, WOS_API_KEY);
}

function getJournalInfo($issn, $year)
{
    $url = 'https://api.clarivate.com/apis/wos-journals/v1/journals?' . http_build_query([
        'q' => $issn,
        'jcrYear' => $year,
        'limit' => 1,
        'page' => 1,
    ]);

    return requestData($url, WOS_JOURNALS_API_KEY);
}

function getQInfo($journalId, $year)
{
    $url = sprintf('https://api.clarivate.com/apis/wos-journals/v1/journals/%s/reports/year/%s', $journalId, $year);

    return requestData($url, WOS_JOURNALS_API_KEY);
}

function isJournalHit($journal)
{
    return is_array($journal) && !empty($journal['hits'][0]['id']);
}

function isQDataAvailable($qData)
{
    return is_array($qData) && (isset($qData['metrics']) || isset($qData['ranks']));
}

function buildCandidateYears($preferredYear)
{
    $currentYear = (int) date('Y');
    $rawYears = [
        $preferredYear,
        $preferredYear ? $preferredYear - 1 : null,
        $preferredYear ? $preferredYear - 2 : null,
        $preferredYear ? $preferredYear - 3 : null,
        $currentYear,
        $currentYear - 1,
        $currentYear - 2,
        $currentYear - 3,
    ];

    $years = [];
    foreach ($rawYears as $year) {
        if (is_numeric($year) && (int) $year > 1900) {
            $years[(int) $year] = (int) $year;
        }
    }

    return array_values($years);
}

function findBestJournalMetrics($issn, $preferredYear)
{
    if (!$issn) {
        return [null, null, null];
    }

    $selectedJournal = null;
    $selectedYear = null;

    foreach (buildCandidateYears($preferredYear) as $year) {
        $journal = getJournalInfo($issn, $year);

        if (isJournalHit($journal)) {
            $selectedJournal = $journal;
            $selectedYear = $year;
            break;
        }
    }

    if (!$selectedJournal || !$selectedYear) {
        return [null, null, null];
    }

    $journalId = $selectedJournal['hits'][0]['id'] ?? null;
    if (!$journalId) {
        return [$selectedJournal, null, $selectedYear];
    }

    $reportYears = [$selectedYear];
    foreach (($selectedJournal['hits'][0]['journalCitationReports'] ?? []) as $report) {
        if (!empty($report['year'])) {
            $reportYears[(int) $report['year']] = (int) $report['year'];
        }
    }

    foreach (buildCandidateYears($preferredYear) as $year) {
        $reportYears[$year] = $year;
    }

    foreach (array_values($reportYears) as $reportYear) {
        $qData = getQInfo($journalId, $reportYear);

        if (isQDataAvailable($qData)) {
            return [$selectedJournal, $qData, $reportYear];
        }
    }

    return [$selectedJournal, null, null];
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing q parameter']);
    exit;
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$userSettings = fetchUserSettings($token);
$displaySettings = buildDisplaySettings($userSettings);

$workData = askOpenAlex($q);

if (!$workData) {
    echo json_encode([
        'input' => $q,
        'error' => 'OpenAlex request failed',
        'displaySettings' => $displaySettings,
        'userSettings' => $userSettings,
        'settingsFetchDebug' => $debugMode ? $settingsFetchDebug : null,
        'debug' => $debugMode ? [
            'openAlexUrlType' => 'curl',
        ] : null,
    ]);
    exit;
}

if (isset($workData['id'])) {
    $work = $workData;
} elseif (isset($workData['results'][0])) {
    $work = $workData['results'][0];
} else {
    echo json_encode([
        'input' => $q,
        'error' => 'No results in OpenAlex',
        'displaySettings' => $displaySettings,
        'userSettings' => $userSettings,
        'settingsFetchDebug' => $debugMode ? $settingsFetchDebug : null,
    ]);
    exit;
}

$primaryLocation = $work['primary_location'] ?? [];
$source = $primaryLocation['source'] ?? [];

$articleTitle = $work['title'] ?? ($work['display_name'] ?? $q);
$journalName = $source['display_name'] ?? ($source['host_organization_name'] ?? null);

$issnL = $source['issn_l'] ?? null;
$issnList = $source['issn'] ?? [];
$issn = $issnL ?? ($issnList[0] ?? null);

$pubYear = $work['publication_year'] ?? null;
$wosData = $issn ? searchWoSAPIAll($issn) : null;
[$journal, $qData, $metricsYear] = findBestJournalMetrics($issn, $pubYear);

if (!$journalName) {
    $journalName = $journal['hits'][0]['name'] ?? null;
}

$quartile = null;
if (!empty($qData['ranks']['jif'][0]['quartile'])) {
    $quartile = $qData['ranks']['jif'][0]['quartile'];
} elseif (!empty($qData['ranks']['jci'][0]['quartile'])) {
    $quartile = $qData['ranks']['jci'][0]['quartile'];
} elseif (!empty($qData['ranks']['articleInfluence'][0]['quartile'])) {
    $quartile = $qData['ranks']['articleInfluence'][0]['quartile'];
}

$response = [
    'isWoS' => $q == $articleTitle,
    'input' => $q,
    'articleTitle' => $articleTitle,
    'doi' => $work['ids']['doi'] ?? getDoi($q),
    'journal' => $journalName,
    'issn' => $issn,
    'year' => $pubYear,
    'metricsYear' => $metricsYear,
    'source' => 'Web of Science',
    'quartile' => $quartile,

    'jif' => $qData['metrics']['impactMetrics']['jif'] ?? null,
    'jif5Years' => $qData['metrics']['impactMetrics']['jif5Years'] ?? null,
    'jifWithoutSelfCitations' => $qData['metrics']['impactMetrics']['jifWithoutSelfCitations'] ?? null,
    'jci' => $qData['metrics']['impactMetrics']['jci'] ?? null,
    'immediacyIndex' => $qData['metrics']['impactMetrics']['immediacyIndex'] ?? null,
    'totalCites' => $qData['metrics']['impactMetrics']['totalCites'] ?? null,

    'articleInfluence' => $qData['metrics']['influenceMetrics']['articleInfluence'] ?? null,
    'eigenFactorScore' => $qData['metrics']['influenceMetrics']['eigenFactor']['score'] ?? null,
    'eigenFactorNormalized' => $qData['metrics']['influenceMetrics']['eigenFactor']['normalized'] ?? null,

    'jifPercentile' => $qData['metrics']['sourceMetrics']['jifPercentile'] ?? null,
    'citableItemsTotal' => $qData['metrics']['sourceMetrics']['citableItems']['total'] ?? null,
    'citableItemsArticlesPercentage' => $qData['metrics']['sourceMetrics']['citableItems']['articlesPercentage'] ?? null,
    'halfLifeCited' => $qData['metrics']['sourceMetrics']['halfLife']['cited'] ?? null,
    'halfLifeCiting' => $qData['metrics']['sourceMetrics']['halfLife']['citing'] ?? null,

    'jifRanks' => $qData['ranks']['jif'] ?? [],
    'articleInfluenceRanks' => $qData['ranks']['articleInfluence'] ?? [],

    'metrics' => [
        'qData' => $qData,
        'journal' => $journal,
        'wosData' => $wosData,
    ],
    'raw' => [
        'qData' => $qData,
        'journal' => $journal,
        'wosData' => $wosData,
    ],
    'userSettings' => $userSettings,
    'settingsFetchDebug' => $debugMode ? $settingsFetchDebug : null,
];

$response = applyDisplaySettings($response, $displaySettings);

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
