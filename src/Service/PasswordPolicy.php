<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

/**
 * S-28: 12 to 1024 characters, no composition rules, no forced changes.
 * Usernames: 3 to 64 characters of letters, digits, dot, dash, underscore.
 */
final class PasswordPolicy
{
    public const int MIN = 12;

    public const int MAX = 1024;

    public const string USERNAME_PATTERN = '/^[A-Za-z0-9._-]{3,64}$/';

    public static function problem(string $password): ?string
    {
        $length = mb_strlen($password);
        if ($length < self::MIN) {
            return sprintf('At least %d characters.', self::MIN);
        }
        if ($length > self::MAX) {
            return sprintf('At most %d characters.', self::MAX);
        }

        return null;
    }

    public static function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
}
