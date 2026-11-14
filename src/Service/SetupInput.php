<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Database\DatabaseSettings;
use Lab09\Disclaimer\Support\Input;

/**
 * The validated content of the setup form. Only the fields listed here are
 * read from the request (E-6).
 */
final readonly class SetupInput
{
    public const int PASSWORD_MIN = 12;

    public const int PASSWORD_MAX = 1024;

    public function __construct(
        public string $baseUrl,
        public DatabaseSettings $database,
        public string $adminUsername,
        #[\SensitiveParameter]
        public string $adminPassword,
        public bool $sampleData,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @return array{0: self|null, 1: array<string, string>} input or null, errors per field
     */
    public static function fromBody(array $body): array
    {
        $in = new Input($body);

        $baseUrl = $in->string('base_url', 255, pattern: '#^https://[a-z0-9.-]+(:\d{1,5})?/?$#i');
        $host = $in->string('db_host', 255, pattern: '/^[A-Za-z0-9.-]+$/');
        $port = $in->int('db_port', 1, 65535, 3306);
        $name = $in->string('db_name', 64, pattern: '/^[A-Za-z0-9_]+$/');
        $user = $in->string('db_user', 64, pattern: '/^[A-Za-z0-9_.-]+$/');
        // Database passwords are taken as typed, spaces included.
        $dbPassword = $in->string('db_password', 1024, required: false, trim: false);
        $username = $in->string('admin_username', 64, pattern: '/^[A-Za-z0-9._-]+$/', minLength: 3);
        // S-28: 12 to 1024 characters, no composition rules, pasting allowed.
        $password = $in->string('admin_password', self::PASSWORD_MAX, minLength: self::PASSWORD_MIN, trim: false);
        $confirm = $in->string('admin_password_confirm', self::PASSWORD_MAX, trim: false);
        $sample = $in->checkbox('sample_data');

        if ($password !== '' && $confirm !== '' && !hash_equals($password, $confirm)) {
            $in->addError('admin_password_confirm', 'Does not match the password.');
        }

        if ($in->errors() !== []) {
            return [null, $in->errors()];
        }

        return [
            new self(rtrim($baseUrl, '/'), new DatabaseSettings($host, $port, $name, $user, $dbPassword), $username, $password, $sample),
            [],
        ];
    }
}
