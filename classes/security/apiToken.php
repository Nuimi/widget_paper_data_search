<?php
namespace Classes\Security;

use PDO;

class ApiToken
{
    public function __construct(private PDO $db, private int $ttl = 28800)
    {
        if ($ttl < 1) {
            throw new \InvalidArgumentException('Token TTL must be positive.');
        }
    }

    public static function fromConfig(): self
    {
        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $db = new PDO('mysql:host=' . $host . ';port=' . $port . ';dbname=' . DB_DATABASE . ';charset=utf8mb4',
            DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        return new self($db, defined('API_TOKEN_TTL_SECONDS') ? (int) API_TOKEN_TTL_SECONDS : 28800);
    }

    public static function bearer(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        return preg_match('/^Bearer ([a-f0-9]{64})$/iD', $header, $match) ? $match[1] : '';
    }

    public function issue(int $userId, ?int $now = null): array
    {
        $now ??= time();
        $token = bin2hex(random_bytes(32));
        $expires = $now + $this->ttl;
        $statement = $this->db->prepare('INSERT INTO tApiToken (tokenHash, FK_userID, issuedAt, expiresAt) VALUES (?, ?, ?, ?)');
        $statement->execute([hash('sha256', $token), $userId, $now, $expires]);
        return ['token' => $token, 'expiresAt' => $expires, 'expiresIn' => $this->ttl];
    }

    public function authenticate(string $token, ?int $now = null): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        $statement = $this->db->prepare('SELECT u.id, t.expiresAt, s.settings FROM tApiToken t
            JOIN tUser u ON u.id = t.FK_userID
            LEFT JOIN tSettings s ON s.FK_userID = u.id
            WHERE t.tokenHash = ? AND t.expiresAt > ? AND t.revokedAt IS NULL AND u.state = 1');
        $statement->execute([hash('sha256', $token), $now ?? time()]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return null;
        }
        $settings = json_decode($user['settings'] ?? '', true);
        $user['settings'] = is_array($settings) ? $settings : [];
        return $user;
    }

    public function revoke(string $token, ?int $now = null): void
    {
        $statement = $this->db->prepare('UPDATE tApiToken SET revokedAt = ? WHERE tokenHash = ? AND revokedAt IS NULL');
        $statement->execute([$now ?? time(), hash('sha256', $token)]);
    }
}
