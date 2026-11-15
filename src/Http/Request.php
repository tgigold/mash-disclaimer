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
     * @param array<string, mixed> $body Parsed POST body, types untouched so
     *                                   validation can reject arrays (E-2)
     * @param array<string, string> $cookies
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $server = [],
        public array $body = [],
        public array $cookies = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        /** @var array<string, mixed> $body */
        $body = $_POST;

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            is_string($path) && $path !== '' ? $path : '/',
            self::strings($_GET),
            self::strings($_SERVER),
            $body,
            self::strings($_COOKIE),
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

        $remote = $this->remoteAddress();
        if ($remote !== '' && in_array($remote, $trustedProxies, true)) {
            return strtolower($this->header('X-Forwarded-Proto') ?? '') === 'https';
        }

        return false;
    }

    public function host(): string
    {
        return strtolower($this->header('Host') ?? '');
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? null;
    }

    public function remoteAddress(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '';
    }

    public function contentLength(): int
    {
        return (int) ($this->server['CONTENT_LENGTH'] ?? 0);
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
