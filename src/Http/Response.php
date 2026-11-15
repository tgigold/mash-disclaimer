<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Http;

/**
 * Outgoing HTTP response; controllers return one, only the front controller sends it.
 */
final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    /** @param array<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store'],
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->body, $this->status, [$name => $value] + $this->headers);
    }

    /**
     * A cookie with the attributes of S-8: __Host- prefix requires Secure and
     * Path=/; HttpOnly and SameSite=Lax always.
     */
    public function withCookie(string $name, string $value, int $maxAge): self
    {
        return $this->withHeader('Set-Cookie', sprintf(
            '%s=%s; Max-Age=%d; Path=/; Secure; HttpOnly; SameSite=Lax',
            $name,
            rawurlencode($value),
            $maxAge,
        ));
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            // Never replace an existing Set-Cookie: PHP's session cookie must survive.
            header($name . ': ' . $value, strcasecmp($name, 'Set-Cookie') !== 0);
        }
        echo $this->body;
    }
}
