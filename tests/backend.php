<?php
require_once __DIR__ . '/../classes/security/apiToken.php';

use Classes\Security\ApiToken;

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

// Exercise the actual token SQL and persistence, isolated from the application database.
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE tUser (id INTEGER PRIMARY KEY, state INTEGER, token TEXT);
    CREATE TABLE tSettings (FK_userID INTEGER UNIQUE, settings TEXT);
    CREATE TABLE tApiToken (tokenHash TEXT PRIMARY KEY, FK_userID INTEGER, issuedAt INTEGER, expiresAt INTEGER, revokedAt INTEGER);
    INSERT INTO tUser VALUES (1, 1, "legacy"), (2, 1, "other");
    INSERT INTO tSettings VALUES (1, \'{"jif":["quartile"]}\');');
$tokens = new ApiToken($db, 60);
$first = $tokens->issue(1, 1000);
$other = $tokens->issue(2, 1000);
$second = $tokens->issue(1, 1000);
check(strlen($first['token']) === 64 && $first['expiresAt'] === 1060, 'Token format and fixed expiry');
check($db->query('SELECT tokenHash FROM tApiToken LIMIT 1')->fetchColumn() !== $first['token'], 'No raw token stored');
check($tokens->authenticate($first['token'], 1059)['settings'] === ['jif' => ['quartile']], 'User settings');
check($tokens->authenticate($other['token'], 1059)['settings'] === [], 'No settings leakage');
check($tokens->authenticate($first['token'], 1060) === null, 'Exact expiry boundary');
check($tokens->authenticate('legacy', 1000) === null, 'Legacy token rejected');
check($tokens->authenticate(str_repeat('a', 64), 1000) === null, 'Unknown token rejected');
$tokens->revoke($first['token'], 1020);
$tokens->revoke($first['token'], 1021);
check($tokens->authenticate($first['token'], 1022) === null, 'Revocation persists and is idempotent');
check($tokens->authenticate($second['token'], 1022) !== null, 'Other session remains valid');
$db->exec('UPDATE tUser SET state = 0 WHERE id = 1');
check($tokens->authenticate($second['token'], 1023) === null, 'Inactive account rejected');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $other['token'];
check(ApiToken::bearer() === $other['token'], 'Bearer header');
unset($_SERVER['HTTP_AUTHORIZATION']);
$_GET['token'] = $other['token'];
check(ApiToken::bearer() === '', 'Query-string token is not accepted');

echo "Token backend: $checks checks passed.\n";
