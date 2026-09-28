<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$debugMode = false; // Never expose transport diagnostics or credentials to clients.
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

set_exception_handler(function ($error) {
    // The cache wraps failures; preserve safe upstream diagnostics through that chain.
    for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
        if ($cause instanceof \Classes\UpstreamRequestException) {
            if ($cause->httpStatus === 503) header('Retry-After: 30');
            sendJsonAndExit(['error' => $cause->getMessage(), 'code' => $cause->reason], $cause->httpStatus);
        }
    }
    error_log('Q-Finder lookup unavailable: ' . get_class($error));
    header('Retry-After: 2');
    sendJsonAndExit(['error' => 'Lookup temporarily unavailable. Please retry.'], 503);
});

header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, OPTIONS');
    sendJsonAndExit(['error' => 'Method not allowed'], 405);
}
require_once dirname(__DIR__) . '/classes/security/apiToken.php';
require_once dirname(__DIR__) . '/classes/MySqlCache.php';
require_once dirname(__DIR__) . '/classes/UpstreamHttp.php';
$token = \Classes\Security\ApiToken::bearer();
if ($token === '') {
    sendJsonAndExit(['error' => 'Authentication required.'], 401);
}

$configPath = dirname(__DIR__) . '/config/config.php';
if (!file_exists($configPath)) {
    sendJsonAndExit([
        'error' => 'Missing config file',
        'configPath' => $debugMode ? $configPath : null,
    ], 500);
}

require_once $configPath;
$user = \Classes\Security\ApiToken::fromConfig()->authenticate($token);
if ($user === null) {
    sendJsonAndExit(['error' => 'Session expired or revoked.'], 401);
}

function cachedUpstream(string $identity, int $ttl, callable $fetch, ?callable $isEmpty = null)
{
    static $cache;
    $cache ??= \Classes\MySqlCache::fromConfig();
    return $cache->remember($identity, $ttl, $fetch, $isEmpty);
}

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

    return cachedUpstream('openalex:' . $openalexUrl,
        defined('OPENALEX_CACHE_TTL_SECONDS') ? (int) OPENALEX_CACHE_TTL_SECONDS : 3600,
        fn() => requestPublicJson($openalexUrl),
        fn($data) => isset($data['results']) && $data['results'] === []);
}

function requestData($url, $apiKey)
{
    return cachedUpstream('clarivate:' . hash('sha256', $apiKey) . ':' . $url,
        defined('WOS_CACHE_TTL_SECONDS') ? (int) WOS_CACHE_TTL_SECONDS : 86400,
        fn() => requestClarivateJson($url, $apiKey),
        fn($data) => isset($data['hits']) && $data['hits'] === []);
}

function requestClarivateJson($url, $apiKey)
{
    return \Classes\UpstreamHttp::request($url, 'Clarivate', $apiKey);
}

function requestPublicJson($url)
{
    return \Classes\UpstreamHttp::request($url, 'OpenAlex',
        defined('OPENALEX_API_KEY') ? OPENALEX_API_KEY : '');
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

$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
if (strlen($q) > 2000) {
    sendJsonAndExit(['error' => 'Query is too long (maximum 2000 bytes).'], 400);
}
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing q parameter']);
    exit;
}

$userSettings = $user['settings'];
$displaySettings = buildDisplaySettings($userSettings);

$workData = askOpenAlex($q);

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
    'isWoS' => isQDataAvailable($qData), // Journal metrics availability, not article indexing evidence.
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
