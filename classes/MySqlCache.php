<?php
namespace Classes;

use PDO;

final class CacheUnavailable extends \RuntimeException {}

/** Shared upstream responses in the existing MySQL/MariaDB database. */
class MySqlCache
{
    private string $database;

    public function __construct(private PDO $db, private string $namespace = 'qfinder:v1:')
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new \InvalidArgumentException('The cache requires MySQL or MariaDB.');
        }
        $this->database = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    }

    public static function fromConfig(): self
    {
        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $db = new PDO('mysql:host=' . $host . ';port=' . $port . ';dbname=' . DB_DATABASE . ';charset=utf8mb4',
            DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_PERSISTENT => false, PDO::ATTR_TIMEOUT => 3]);
        return new self($db, defined('LOOKUP_CACHE_NAMESPACE') ? LOOKUP_CACHE_NAMESPACE : 'qfinder:v1:');
    }

    public function remember(string $identity, int $ttl, callable $fetch, ?callable $isEmpty = null): mixed
    {
        if ($ttl < 1) {
            throw new \InvalidArgumentException('Cache TTL must be positive.');
        }
        $key = hash('sha256', $this->namespace . $identity);
        // Named locks are server-wide and limited to 64 characters in MySQL.
        $lock = hash('sha256', 'qfinder-cache:' . $this->database . ':' . $key);
        try {
            $cached = $this->read($key);
            if ($cached !== null) {
                return $cached;
            }
            $statement = $this->db->prepare('SELECT GET_LOCK(?, 1)');
            $statement->execute([$lock]);
            if ((int) $statement->fetchColumn() !== 1) {
                throw new CacheUnavailable('Lookup already in progress; please retry.');
            }
            try {
                // A previous lock holder may have completed while we waited.
                $cached = $this->read($key);
                if ($cached !== null) {
                    return $cached;
                }
                // Bound housekeeping work; expired rows are never served even if retained.
                $this->db->exec('DELETE FROM tLookupCache WHERE expiresAt <= UNIX_TIMESTAMP() LIMIT 100');
                $data = $fetch();
                if (is_array($data) && !isset($data['error'])) {
                    $lifetime = $isEmpty && $isEmpty($data) ? min($ttl, 300) : $ttl;
                    $statement = $this->db->prepare('INSERT INTO tLookupCache (cacheKey, payload, expiresAt)
                        VALUES (?, ?, UNIX_TIMESTAMP() + ?)
                        ON DUPLICATE KEY UPDATE payload = VALUES(payload), expiresAt = VALUES(expiresAt)');
                    $statement->execute([$key, json_encode($data, JSON_THROW_ON_ERROR), $lifetime]);
                }
                return $data;
            } finally {
                $statement = $this->db->prepare('SELECT RELEASE_LOCK(?)');
                $statement->execute([$lock]);
            }
        } catch (\Throwable $error) {
            // No uncached fallback: a database failure must not cause an API request storm.
            throw new CacheUnavailable('Lookup temporarily unavailable; please retry.', 0, $error);
        }
    }

    private function read(string $key): ?array
    {
        $statement = $this->db->prepare('SELECT payload FROM tLookupCache
            WHERE cacheKey = ? AND expiresAt > UNIX_TIMESTAMP()');
        $statement->execute([$key]);
        $raw = $statement->fetchColumn();
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) && !isset($data['error']) ? $data : null;
    }
}
