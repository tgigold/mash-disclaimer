<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\User;

/**
 * Outcome of a login attempt.
 */
final readonly class LoginResult
{
    private function __construct(
        public ?User $user,
        public ?int $lockedForSeconds,
        public ?string $deviceToken,
    ) {
    }

    public static function success(User $user, ?string $deviceToken): self
    {
        return new self($user, null, $deviceToken);
    }

    public static function failed(): self
    {
        return new self(null, null, null);
    }

    public static function locked(int $seconds): self
    {
        return new self(null, $seconds, null);
    }
}
