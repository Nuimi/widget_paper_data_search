<?php
// Included by mysql.php: exercise the actual standalone endpoint with seeded cache.
// curl_init is disabled in the child PHP process, so no external API can be called.
function checkLookupEndpoint(PDO $db, string $schema, string $dsn): void
{
    $root = dirname(__DIR__);
    $directory = $root . '/tmp/' . $schema;
    $createdFiles = [];
    $createdDirs = [];
    $parts = [];
    foreach (explode(';', substr($dsn, 6)) as $part) {
        if (str_contains($part, '=')) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }
    }
    if (!is_dir($root . '/tmp')) mkdir($root . '/tmp');
    try {
        foreach (['', '/classes', '/classes/security', '/presenters', '/config'] as $suffix) {
            mkdir($directory . $suffix);
            $createdDirs[] = $directory . $suffix;
        }
        foreach (['classes/UpstreamHttp.php', 'classes/MySqlCache.php', 'classes/security/apiToken.php', 'classes/defaultClass.php', 'presenters/api.php', 'presenters/ajax.php'] as $file) {
            copy($root . '/' . $file, $directory . '/' . $file);
            $createdFiles[] = $directory . '/' . $file;
        }
        $configuration = [
            'DB_HOST' => $parts['host'] ?? '127.0.0.1', 'DB_PORT' => (int) ($parts['port'] ?? 3306),
            'DB_DATABASE' => $schema, 'DB_USERNAME' => getenv('QF_TEST_MYSQL_USER') ?: 'root',
            'DB_PASSWORD' => getenv('QF_TEST_MYSQL_PASSWORD') ?: '',
            'WOS_API_KEY' => 'test-wos', 'WOS_JOURNALS_API_KEY' => 'test-journals',
            'LOOKUP_CACHE_NAMESPACE' => 'endpoint:',
        ];
        $source = "<?php\n";
        foreach ($configuration as $key => $value) $source .= 'define(' . var_export($key, true) . ', ' . var_export($value, true) . ");\n";
        file_put_contents($directory . '/config/config.php', $source);
        $createdFiles[] = $directory . '/config/config.php';
        file_put_contents($directory . '/runner.php', <<<'PHP'
<?php
$input = json_decode(file_get_contents(__DIR__ . '/request.json'), true);
$_GET = $input['get'];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = $input['method'];
$_SERVER['HTTP_AUTHORIZATION'] = $input['header'];
ob_start();
register_shutdown_function(function () {
    $body = ob_get_clean();
    echo json_encode(['status' => http_response_code() ?: 200, 'data' => json_decode($body, true)]);
});
if ($input['endpoint'] === 'lookup') {
    require __DIR__ . '/presenters/api.php';
} else {
    require __DIR__ . '/config/config.php';
    require __DIR__ . '/classes/defaultClass.php';
    require __DIR__ . '/classes/security/apiToken.php';
    require __DIR__ . '/presenters/ajax.php';
    $ajax = (new ReflectionClass('Ajax'))->newInstanceWithoutConstructor();
    $methods = ['settings' => 'renderUserSettings', 'revoke' => 'renderRevokeToken', 'login' => 'renderLogIn'];
    $ajax->{$methods[$input['endpoint']]}();
}
PHP);
        $createdFiles[] = $directory . '/runner.php';
        $createdFiles[] = $directory . '/request.json';
        $run = function (string $token = '', array $get = ['q' => 'A cached article'], string $method = 'GET', string $endpoint = 'lookup') use ($directory): array {
            file_put_contents($directory . '/request.json', json_encode([
                'header' => $token === '' ? '' : 'Bearer ' . $token, 'get' => $get, 'method' => $method, 'endpoint' => $endpoint,
            ]));
            $process = proc_open([PHP_BINARY, '-d', 'disable_functions=curl_init', '-d', 'display_errors=0', $directory . '/runner.php'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) throw new RuntimeException('Cannot start endpoint test');
            fclose($pipes[0]);
            $result = stream_get_contents($pipes[1]); fclose($pipes[1]);
            $errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
            $status = proc_close($process);
            if ($status !== 0) throw new RuntimeException('Endpoint test process failed: ' . $errors);
            $decoded = json_decode($result, true);
            if (!is_array($decoded)) throw new RuntimeException('Endpoint did not return test JSON');
            return $decoded;
        };

        $db->exec('UPDATE tUser SET state = 1 WHERE id = 1');
        $db->exec('DELETE FROM tSettings WHERE FK_userID = 1');
        $tokens = new \Classes\Security\ApiToken($db, 60);
        $valid = $tokens->issue(1)['token'];
        $expired = $tokens->issue(1, time() - 60)['token'];
        $revoked = $tokens->issue(1)['token']; $tokens->revoke($revoked);
        $cache = new \Classes\MySqlCache($db, 'endpoint:');
        $seed = fn($identity, $payload) => $cache->remember($identity, 3600, fn() => $payload);
        $work = ['id' => 'https://openalex.org/W1', 'title' => 'A cached article', 'publication_year' => 2025,
            'primary_location' => ['source' => ['display_name' => 'Test journal', 'issn_l' => '1234-5678']]];
        $seed('openalex:https://api.openalex.org/works?search=A+cached+article&per-page=1', ['results' => [$work]]);
        $seed('openalex:https://api.openalex.org/works/https://doi.org/10.1234%2Fexample', $work);
        $seed('clarivate:' . hash('sha256', 'test-wos') . ':https://wos-api.clarivate.com/api/wos/?' . http_build_query([
            'databaseId' => 'WOS', 'usrQuery' => 'IS=1234-5678', 'count' => 10, 'firstRecord' => 1, 'sortField' => 'LD+D',
        ]), ['records' => []]);
        $seed('clarivate:' . hash('sha256', 'test-journals') . ':https://api.clarivate.com/apis/wos-journals/v1/journals?' . http_build_query([
            'q' => '1234-5678', 'jcrYear' => 2025, 'limit' => 1, 'page' => 1,
        ]), ['hits' => [['id' => 'J1', 'name' => 'Test journal']]]);
        $seed('clarivate:' . hash('sha256', 'test-journals') . ':https://api.clarivate.com/apis/wos-journals/v1/journals/J1/reports/year/2025',
            ['metrics' => ['impactMetrics' => ['jif' => 3.2]], 'ranks' => ['jif' => [['quartile' => 'Q2']]]]);

        check($run()['status'] === 401, 'Endpoint rejects missing token before cache');
        check($run('', ['q' => 'A cached article', 'token' => $valid])['status'] === 401, 'Endpoint rejects URL token');
        check($run($expired)['status'] === 401, 'Endpoint rejects expired token on cached query');
        check($run($revoked)['status'] === 401, 'Endpoint rejects revoked token on cached query');
        check($run($valid, ['q' => ''])['status'] === 400, 'Endpoint rejects empty query');
        check($run($valid, ['q' => str_repeat('a', 2001)])['status'] === 400, 'Endpoint rejects oversized query');
        check($run('', [], 'OPTIONS')['status'] === 204, 'CORS preflight');
        $result = $run($valid);
        check($result['status'] === 200 && $result['data']['jif'] === 3.2 && $result['data']['articleTitle'] === 'A cached article', 'Full lookup succeeds with external transport disabled');
        check($run($valid, ['q' => '10.1234/example'])['data']['quartile'] === 'Q2', 'Manual DOI reuses journal cache');
        $db->exec("INSERT INTO tSettings (FK_userID, settings) VALUES (1, '{\"jif\":{\"jifQ\":true}}')");
        $result = $run($valid);
        check(!isset($result['data']['jif']) && $result['data']['jifRanks'][0]['quartile'] === 'Q2', 'Current settings are applied after shared cache retrieval');
        check($run($valid, [], 'GET', 'settings')['data']['settings'] === ['jif' => ['jifQ' => true]], 'Actual settings endpoint returns current preferences');
        check($run('', [], 'GET', 'settings')['status'] === 401, 'Settings endpoint requires bearer authentication');
        check($run($valid, [], 'GET', 'revoke')['status'] === 405, 'Revocation requires POST');
        check($run('', [], 'POST', 'revoke')['status'] === 401, 'Revocation requires bearer authentication');
        check($run($valid, [], 'POST', 'revoke')['data']['ok'] === true, 'Actual revocation endpoint succeeds');
        check($run($valid, [], 'POST', 'revoke')['data']['ok'] === true, 'Actual revocation endpoint is idempotent');
        check($run($valid, [], 'GET', 'settings')['status'] === 401, 'Revoked session cannot access settings');
        check($run($valid)['status'] === 401, 'Revocation blocks previously successful cached lookup');
        check($run('', [], 'GET', 'login')['status'] === 405, 'Login requires POST');
        check($run('', [], 'POST', 'login')['status'] === 400, 'Empty login rejected before LDAP');
    } finally {
        foreach (array_reverse($createdFiles) as $file) if (is_file($file)) unlink($file);
        foreach (array_reverse($createdDirs) as $dir) if (is_dir($dir)) rmdir($dir);
    }
}
