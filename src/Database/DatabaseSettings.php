<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Database;

/**
 * Connection data for the database. Comes from data/config.php or the setup form.
 */
final readonly class DatabaseSettings
{
    public function __construct(
        public string $host,
        public int $port,
        public string $name,
        public string $user,
        #[\SensitiveParameter]
        public string $password,
    ) {
    }

    /** @return array{host: string, port: int, name: string, user: string, password: string} */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'name' => $this->name,
            'user' => $this->user,
            'password' => $this->password,
        ];
    }

    /** Hides the password in var_dump(), print_r() and stack traces. */
    public function __debugInfo(): array
    {
        return ['host' => $this->host, 'port' => $this->port, 'name' => $this->name, 'user' => $this->user];
    }
}
