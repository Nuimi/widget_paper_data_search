<?php
require_once __DIR__ . '/../classes/UpstreamHttp.php';
require_once __DIR__ . '/../classes/MySqlCache.php';

use Classes\UpstreamHttp;
use Classes\UpstreamRequestException;

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function fails(callable $call, string $reason, int $status): void {
    try { $call(); } catch (UpstreamRequestException $error) {
        check($error->reason === $reason && $error->httpStatus === $status, $reason);
        check(!str_contains($error->getMessage(), 'PRIVATE_SENTINEL'), 'Upstream body is never reflected');
        return;
    }
    throw new RuntimeException('Expected failure: ' . $reason);
}

$options = UpstreamHttp::tlsOptions();
check($options[CURLOPT_SSL_VERIFYPEER] === true && $options[CURLOPT_SSL_VERIFYHOST] === 2, 'TLS validation stays enabled');
if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
    check($options[CURLOPT_SSL_OPTIONS] === CURLSSLOPT_NATIVE_CA, 'Windows native trust store is enabled');
}
check(UpstreamHttp::decode('{"results":[{"id":"W1"}]}', 200, 'application/json; charset=utf-8', 0, 'OpenAlex')['results'][0]['id'] === 'W1', 'Successful search');
check(UpstreamHttp::decode('{"id":"W1"}', 200, 'application/json', 0, 'OpenAlex')['id'] === 'W1', 'Successful DOI');
check(UpstreamHttp::decode('{}', 404, 'application/json', 0, 'OpenAlex') === ['results' => []], 'Missing DOI is an empty result');
check(UpstreamHttp::decode('{}', 404, 'application/json', 0, 'Clarivate') === ['hits' => []], 'Missing journal preserves year fallback');
fails(fn() => UpstreamHttp::decode(false, 0, '', 60, 'OpenAlex'), 'openalex_tls', 502);
fails(fn() => UpstreamHttp::decode(false, 0, '', 77, 'Clarivate'), 'clarivate_tls', 502);
fails(fn() => UpstreamHttp::decode(false, 0, '', 28, 'OpenAlex'), 'openalex_timeout', 504);
fails(fn() => UpstreamHttp::decode(false, 0, '', 6, 'OpenAlex'), 'openalex_connection', 502);
foreach ([401, 403] as $status) {
    fails(fn() => UpstreamHttp::decode('PRIVATE_SENTINEL', $status, 'text/html', 0, 'OpenAlex'), 'openalex_authentication', 502);
}
foreach ([409, 429] as $status) {
    fails(fn() => UpstreamHttp::decode('PRIVATE_SENTINEL', $status, 'text/html', 0, 'OpenAlex'), 'openalex_rate_limit', 503);
}
fails(fn() => UpstreamHttp::decode('{"error":"Search temporarily unavailable","message":"PRIVATE_SENTINEL"}', 503, 'application/json', 0, 'OpenAlex'), 'openalex_search_unavailable', 503);
fails(fn() => UpstreamHttp::decode('PRIVATE_SENTINEL', 500, 'text/html', 0, 'OpenAlex'), 'openalex_http', 503);
fails(fn() => UpstreamHttp::decode('PRIVATE_SENTINEL', 200, 'application/json', 0, 'OpenAlex'), 'openalex_invalid_response', 502);
fails(fn() => UpstreamHttp::decode('{"error":"PRIVATE_SENTINEL"}', 200, 'application/json', 0, 'OpenAlex'), 'openalex_invalid_response', 502);
fails(fn() => UpstreamHttp::decode('{"results":[]}', 200, 'text/html', 0, 'OpenAlex'), 'openalex_invalid_response', 502);

// Execute the real exception handler, including the cache wrapper, in a child process.
$source = file_get_contents(__DIR__ . '/../presenters/api.php');
$begin = strpos($source, 'set_exception_handler(');
$end = strpos($source, "\nheader('Access-Control-Allow-Headers:", $begin);
$handler = substr($source, $begin, $end - $begin);
$preamble = 'require ' . var_export(__DIR__ . '/../classes/UpstreamHttp.php', true) . '; require '
    . var_export(__DIR__ . '/../classes/MySqlCache.php', true) . '; '
    . 'function sendJsonAndExit($payload, $status) { echo json_encode([$payload, $status]); exit; } ';
$script = $preamble . $handler . ' throw new \\Classes\\CacheUnavailable("PRIVATE_SENTINEL", 0, new \\Classes\\UpstreamRequestException("Safe message", "openalex_tls", 502));';
// Use stdin rather than -r: uncaught exceptions must reach PHP\'s normal script handler.
$process = proc_open([PHP_BINARY], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
fwrite($pipes[0], "<?php\n" . $script); fclose($pipes[0]);
$output = stream_get_contents($pipes[1]); fclose($pipes[1]);
$errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
$exit = proc_close($process);
check($exit === 0 && json_decode($output, true) === [['error' => 'Safe message', 'code' => 'openalex_tls'], 502], 'Endpoint unwraps safe cache errors without exposing internal details: ' . $errors);
echo "$checks upstream checks passed.\n";

// Explicit opt-in: uses the configured server key, calls OpenAlex only, never WoS or DB.
if (in_array('--live', $argv, true)) {
    require __DIR__ . '/../config/config.php';
    $key = defined('OPENALEX_API_KEY') ? OPENALEX_API_KEY : '';
    $failed = false;
    foreach ([
        'DOI' => 'https://api.openalex.org/works/https://doi.org/10.1038/nature12373',
        'Title' => 'https://api.openalex.org/works?search=atspR&per-page=1',
    ] as $label => $url) {
        try {
            $data = UpstreamHttp::request($url, 'OpenAlex', $key);
            echo "$label: OK; " . ($data['title'] ?? $data['results'][0]['title'] ?? 'no results') . "\n";
        } catch (UpstreamRequestException $error) {
            $failed = true;
            echo "$label: {$error->reason}: {$error->getMessage()}\n";
        }
    }
    exit($failed ? 1 : 0);
}
