<?php

declare(strict_types=1);

namespace Lab09\Disclaimer;

use Lab09\Disclaimer\Database\DatabaseSettings;

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
        // Database connection; written by the setup. null = not installed.
        'db' => null,
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

    /** The setup writes the config only after everything else succeeded. */
    public function isInstalled(): bool
    {
        return $this->database() !== null;
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

    public function database(): ?DatabaseSettings
    {
        $db = $this->values['db'] ?? null;
        if (!is_array($db)) {
            return null;
        }

        $host = $db['host'] ?? null;
        $name = $db['name'] ?? null;
        $user = $db['user'] ?? null;
        $password = $db['password'] ?? '';
        $port = $db['port'] ?? 3306;

        if (!is_string($host) || !is_string($name) || !is_string($user) || !is_string($password) || !is_int($port)) {
            throw new \RuntimeException('The database section of the config is incomplete.');
        }

        return new DatabaseSettings($host, $port, $name, $user, $password);
    }

    /**
     * The PHP source of a config file with these values, as the setup writes it.
     *
     * @param array<string, mixed> $values
     */
    public static function export(array $values): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n"
            . "// Written by the setup. Lives only on the server, never in the repo (G-14).\n"
            . "// Every key is optional; defaults live in src/Config.php.\n\n"
            . 'return ' . var_export($values, true) . ";\n";
    }
}
