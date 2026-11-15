<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Domain;

/**
 * An admin account. The password hash never leaves the repository layer except
 * for verification in AuthService.
 */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $username,
        public bool $isActive,
        public int $sessionEpoch,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastLoginAt,
        public ?string $createdByName = null,
    ) {
    }
}
