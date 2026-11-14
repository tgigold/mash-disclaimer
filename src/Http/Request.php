<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Http;

/**
 * Immutable view of the incoming HTTP request, built once from the PHP globals.
 */
final readonly class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $server
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? $path : '/',
            self::strings($_GET),
            self::strings($_SERVER),
        );
    }

    /**
     * Whether the request reached us via HTTPS. X-Forwarded-Proto only counts
     * when it comes from a configured proxy, never from an arbitrary client (S-6).
     *
     * @param list<string> $trustedProxies
     */
    public function isHttps(array $trustedProxies = []): bool
    {
        $https = strtolower($this->server['HTTPS'] ?? '');
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        $remote = $this->server['REMOTE_ADDR'] ?? '';
        if ($remote !== '' && in_array($remote, $trustedProxies, true)) {
            return strtolower($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        }

        return false;
    }

    public function host(): string
    {
        return strtolower($this->server['HTTP_HOST'] ?? '');
    }

    /**
     * Keeps only scalar values; nested arrays from query strings like a[]=1 are
     * dropped because no route expects them.
     *
     * @param array<mixed> $values
     * @return array<string, string>
     */
    private static function strings(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_scalar($value)) {
                $result[(string) $key] = (string) $value;
            }
        }

        return $result;
    }
}
