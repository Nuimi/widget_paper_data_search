<?php
// Explicit test connection only: never reads the application's private config.
// Requires CREATE/DROP DATABASE privileges; creates and removes its own random schema.
require_once __DIR__ . '/../classes/MySqlCache.php';
require_once __DIR__ . '/../classes/security/apiToken.php';

use Classes\MySqlCache;
use Classes\CacheUnavailable;
use Classes\Security\ApiToken;

$dsn = getenv('QF_TEST_MYSQL_DSN');
if (!$dsn || !str_starts_with($dsn, 'mysql:')) {
    fwrite(STDERR, "Set QF_TEST_MYSQL_DSN to an explicit MySQL/MariaDB test connection.\n");
    exit(1);
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 3];
$connect = fn() => new PDO($dsn, getenv('QF_TEST_MYSQL_USER') ?: 'root', getenv('QF_TEST_MYSQL_PASSWORD') ?: '', $options);
$admin = $connect();
$schema = 'qfinder_test_' . bin2hex(random_bytes(6));
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$admin->exec("CREATE DATABASE `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $a = $connect(); $b = $connect();
    $a->exec("USE `$schema`"); $b->exec("USE `$schema`");
    // Apply the shipped schema in isolation, without its production database selection.
    $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
    $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS `widget`.*?;/s', '', $sql);
    $sql = str_replace('USE `widget`;', '', $sql);
    $a->exec($sql);
    $a->exec(file_get_contents(__DIR__ . '/../database/migrations/001_api_tokens.sql'));
    $a->exec(file_get_contents(__DIR__ . '/../database/migrations/002_lookup_cache.sql'));
    check((int) $a->query('SELECT COUNT(*) FROM tLookupCache')->fetchColumn() === 0, 'Schema and migrations are idempotent');

    $cache = new MySqlCache($a, 'test:'); $peer = new MySqlCache($b, 'test:');
    $calls = 0;
    $load = function () use (&$calls) { $calls++; return ['metrics' => ['jif' => 1], 'title' => 'Žluťoučký časopis']; };
    $first = $cache->remember('issn:1234:2025', 60, $load);
    check($first['metrics']['jif'] === 1, 'Miss fetches upstream');
    check($peer->remember('issn:1234:2025', 60, $load) === $first && $calls === 1, 'Independent connection reuses shared JSON, including UTF-8');
    $peer->remember('issn:1234:2024', 60, $load);
    check($calls === 2, 'Years are isolated');
    (new MySqlCache($b, 'other:'))->remember('issn:1234:2025', 60, $load);
    check($calls === 3, 'Namespaces are isolated');
    $a->exec('UPDATE tLookupCache SET expiresAt = UNIX_TIMESTAMP()');
    $cache->remember('issn:1234:2025', 60, $load);
    check($calls === 4, 'Exact expiry boundary refreshes');
    check((int) $a->query('SELECT COUNT(*) FROM tLookupCache')->fetchColumn() === 1, 'Miss removes expired records');

    $emptyCalls = 0;
    $empty = function () use (&$emptyCalls) { $emptyCalls++; return ['hits' => []]; };
    $cache->remember('empty', 86400, $empty, fn($d) => $d['hits'] === []);
    $peer->remember('empty', 86400, $empty, fn($d) => $d['hits'] === []);
    check($emptyCalls === 1, 'Empty successful results are reused');
    $remaining = (int) $a->query("SELECT expiresAt - UNIX_TIMESTAMP() FROM tLookupCache WHERE payload = '{\"hits\":[]}'")->fetchColumn();
    check($remaining > 0 && $remaining <= 300, 'Negative caching is limited to five minutes');
    $errors = 0;
    $error = function () use (&$errors) { $errors++; return ['error' => 'upstream unavailable']; };
    $cache->remember('error', 60, $error); $peer->remember('error', 60, $error);
    check($errors === 2, 'Error responses are not cached');
    $failed = 0;
    $transport = function () use (&$failed) { $failed++; return false; };
    $cache->remember('transport', 60, $transport); $peer->remember('transport', 60, $transport);
    check($failed === 2, 'Transport failures are not cached');

    $cache->remember('corrupt', 60, $load);
    $a->exec("UPDATE tLookupCache SET payload = 'not-json'");
    $before = $calls;
    $cache->remember('corrupt', 60, $load);
    check($calls === $before + 1, 'Malformed stored JSON is refreshed');

    $blocked = false;
    $cache->remember('contended', 60, function () use ($peer, &$blocked) {
        try {
            $peer->remember('contended', 60, function () { throw new LogicException('Duplicate upstream query'); });
        } catch (CacheUnavailable $e) {
            $blocked = $e->getPrevious() instanceof CacheUnavailable;
        }
        return ['done' => true];
    });
    check($blocked, 'Second connection cannot fetch while first owns lock');
    check($peer->remember('contended', 60, fn() => []) === ['done' => true], 'Completed result is shared after lock release');
    try { $cache->remember('throws', 60, function () { throw new RuntimeException('upstream'); }); }
    catch (CacheUnavailable $e) { check($e->getPrevious()->getMessage() === 'upstream', 'Fetch exceptions are reported'); }
    check($peer->remember('throws', 60, fn() => ['recovered' => true]) === ['recovered' => true], 'Lock released after exception');
    $a->exec('RENAME TABLE tLookupCache TO tLookupCacheOffline');
    $before = $calls;
    try { $cache->remember('offline', 60, $load); throw new LogicException('Expected cache failure'); }
    catch (CacheUnavailable $e) { check($calls === $before, 'Unavailable cache fails before external fetch'); }
    finally { $a->exec('RENAME TABLE tLookupCacheOffline TO tLookupCache'); }

    $a->exec("INSERT INTO tUser (id,email,lastSearch,token,permission,state) VALUES (1,'cache-test@example.invalid',NOW(),'legacy','1',1)");
    $a->exec("INSERT INTO tSettings (FK_userID,settings) VALUES (1,'{\"jif\":[\"quartile\"]}')");
    $tokens = new ApiToken($a, 60); $tokenPeer = new ApiToken($b, 60);
    $token = $tokens->issue(1, 1000);
    check($tokenPeer->authenticate($token['token'], 1059)['settings'] === ['jif' => ['quartile']], 'MySQL token authentication and settings');
    check($tokenPeer->authenticate($token['token'], 1060) === null, 'MySQL expiry boundary');
    $tokens->revoke($token['token'], 1020);
    check($tokenPeer->authenticate($token['token'], 1021) === null, 'MySQL revocation across connections');
    check($tokenPeer->authenticate('legacy', 1021) === null, 'Old user token rejected');
    $token = $tokens->issue(1, 1000);
    $a->exec('UPDATE tUser SET state = 0 WHERE id = 1');
    check($tokenPeer->authenticate($token['token'], 1021) === null, 'Inactive user rejected');
    require_once __DIR__ . '/lookup.php';
    checkLookupEndpoint($a, $schema, $dsn);
    echo "MySQL/MariaDB integration: $checks checks passed (" . $a->query('SELECT VERSION()')->fetchColumn() . ").\n";
} finally {
    // Only this run's randomly generated test schema is eligible for cleanup.
    if (!preg_match('/^qfinder_test_[a-f0-9]{12}$/D', $schema)) throw new RuntimeException('Unsafe test schema name');
    $a = $b = null;
    $admin->exec("DROP DATABASE `$schema`");
}
