<?php
namespace Classes;

/** Only fixed, credential-free messages from this exception may reach the popup. */
final class UpstreamRequestException extends \RuntimeException
{
    public function __construct(string $message, public string $reason, public int $httpStatus = 502)
    {
        parent::__construct($message);
    }
}

final class UpstreamHttp
{
    public static function tlsOptions(): array
    {
        $options = [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
        $bundle = defined('UPSTREAM_CA_BUNDLE') ? trim(UPSTREAM_CA_BUNDLE) : '';
        if ($bundle !== '') {
            $options[CURLOPT_CAINFO] = $bundle;
        } elseif (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            // XAMPP's bundled PEM can be years old; use Windows trust as well.
            $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        return $options;
    }

    public static function request(string $url, string $service, string $apiKey = ''): array
    {
        if (!in_array($service, ['OpenAlex', 'Clarivate'], true)) {
            throw new \InvalidArgumentException('Unknown upstream service.');
        }
        $headers = ['Accept: application/json', 'User-Agent: Q-Quartile-Widget/1.1.0'];
        if (trim($apiKey) !== '') {
            $headers[] = ($service === 'OpenAlex' ? 'Authorization: Bearer ' : 'X-ApiKey: ') . trim($apiKey);
        }
        $ch = curl_init($url);
        try {
            curl_setopt_array($ch, self::tlsOptions() + [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $raw = curl_exec($ch);
            return self::decode($raw, (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
                (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE), curl_errno($ch), $service);
        } finally {
            curl_close($ch);
        }
    }

    /** Kept separate so transport and HTTP failure handling can be regression-tested. */
    public static function decode(string|false $raw, int $status, string $contentType, int $errno, string $service): array
    {
        if ($raw === false || $errno !== 0) {
            $reason = match ($errno) {
                60, 77 => 'tls',
                28 => 'timeout',
                default => 'connection',
            };
            $message = match ($reason) {
                'tls' => "$service HTTPS certificate verification failed on the server. Contact the administrator.",
                'timeout' => "$service request timed out. Please retry.",
                default => "The server could not connect to $service. Please retry or contact the administrator.",
            };
            self::fail($service, $status, $errno, $reason, $message, $reason === 'timeout' ? 504 : 502);
        }
        if ($status === 404) {
            return $service === 'OpenAlex' ? ['results' => []] : ['hits' => []];
        }
        if ($status === 401 || $status === 403) {
            self::fail($service, $status, $errno, 'authentication',
                "$service rejected server access. Ask the administrator to check the $service API key.");
        }
        if ($status === 429 || ($service === 'OpenAlex' && $status === 409)) {
            self::fail($service, $status, $errno, 'rate_limit',
                "$service usage limit reached. Retry later or ask the administrator to check the API key and quota.", 503);
        }
        if ($status < 200 || $status >= 300) {
            $data = json_decode($raw, true);
            if ($service === 'OpenAlex' && $status === 503 && is_array($data)
                && ($data['error'] ?? null) === 'Search temporarily unavailable') {
                self::fail($service, $status, $errno, 'search_unavailable',
                    'OpenAlex search is temporarily unavailable. Retry later or ask the administrator to configure an OpenAlex API key.', 503);
            }
            self::fail($service, $status, $errno, 'http', "$service returned HTTP $status. Please retry later.", 503);
        }
        $data = json_decode($raw, true);
        if (stripos($contentType, 'application/json') === false || !is_array($data)
            || isset($data['error']) || isset($data['errors'])) {
            self::fail($service, $status, $errno, 'invalid_response', "$service returned an invalid response. Please retry later.");
        }
        return $data;
    }

    private static function fail(string $service, int $status, int $errno, string $reason, string $message, int $httpStatus = 502): never
    {
        // Never log URLs, request headers, tokens or untrusted upstream bodies.
        error_log("Q-Finder $service failure: reason=$reason http=$status curl=$errno");
        throw new UpstreamRequestException($message, strtolower($service) . '_' . $reason, $httpStatus);
    }
}
