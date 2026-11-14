<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use PDO;

/**
 * Admin accounts in v4_users.
 */
final readonly class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function count(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM v4_users');

        return $stmt === false ? 0 : (int) $stmt->fetchColumn();
    }

    /** @return int the new user's id */
    public function create(string $username, string $passwordHash, ?int $createdBy): int
    {
        $this->pdo->prepare(
            'INSERT INTO v4_users (username, password_hash, is_active, session_epoch, created_at, created_by, password_changed_at)
             VALUES (:username, :hash, 1, 0, UTC_TIMESTAMP(), :created_by, UTC_TIMESTAMP())',
        )->execute(['username' => $username, 'hash' => $passwordHash, 'created_by' => $createdBy]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{id: int, username: string, password_hash: string, is_active: bool}|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, password_hash, is_active FROM v4_users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'password_hash' => (string) $row['password_hash'],
            'is_active' => (bool) $row['is_active'],
        ];
    }
}
