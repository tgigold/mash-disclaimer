<?php

declare(strict_types=1);

namespace Lab09\Disclaimer;

/**
 * Read-only configuration with defaults in code (G-13): a config file only has
 * to contain what differs, and missing keys never break an update.
 */
final class Config
{
    /** @var array<string, mixed> */
    private const array DEFAULTS = [
        // Canonical URL without trailing slash, e.g. https://tater.lab09.de.
        // null until the setup has written the config; the HTTPS redirect in
        // public/.htaccess covers that phase.
        'base_url' => null,
        // Proxies whose X-Forwarded-Proto header may be trusted (S-6).
        'trusted_proxies' => [],
    ];

    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values = [])
    {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            return new self(self::DEFAULTS);
        }

        $values = require $file;
        if (!is_array($values)) {
            throw new \RuntimeException('The config file must return an array.');
        }

        return new self(array_replace(self::DEFAULTS, $values));
    }

    /** @param array<string, mixed> $overrides */
    public static function withDefaults(array $overrides = []): self
    {
        return new self(array_replace(self::DEFAULTS, $overrides));
    }

    public function baseUrl(): ?string
    {
        $url = $this->values['base_url'] ?? null;

        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
    }

    /** @return list<string> */
    public function trustedProxies(): array
    {
        $proxies = $this->values['trusted_proxies'] ?? [];

        return is_array($proxies) ? array_values(array_filter($proxies, 'is_string')) : [];
    }
}
