<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use Lab09\Disclaimer\Domain\User;
use PDO;

/**
 * Admin accounts in v4_users.
 */
final readonly class UserRepository
{
    private const string SELECT = 'SELECT u.id, u.username, u.is_active, u.session_epoch, u.created_at, u.last_login_at,
            c.username AS created_by_name
        FROM v4_users u LEFT JOIN v4_users c ON c.id = u.created_by';

    public function __construct(private PDO $pdo)
    {
    }

    public function count(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM v4_users');

        return $stmt === false ? 0 : (int) $stmt->fetchColumn();
    }

    public function countActive(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM v4_users WHERE is_active = 1');

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

    public function find(int $id): ?User
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE u.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @return list<User> */
    public function all(): array
    {
        $stmt = $this->pdo->query(self::SELECT . ' ORDER BY u.is_active DESC, u.username');

        return $stmt === false ? [] : array_values(array_map($this->hydrate(...), $stmt->fetchAll()));
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

    public function passwordHash(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT password_hash FROM v4_users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();

        return is_string($hash) ? $hash : null;
    }

    /**
     * New password; raises the session epoch so every existing session of this
     * user ends (S-10).
     */
    public function setPassword(int $id, string $passwordHash): void
    {
        $this->pdo->prepare(
            'UPDATE v4_users SET password_hash = :hash, password_changed_at = UTC_TIMESTAMP(), session_epoch = session_epoch + 1
             WHERE id = :id',
        )->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    /** Only the hash, e.g. after password_needs_rehash; sessions stay valid. */
    public function rehash(int $id, string $passwordHash): void
    {
        $this->pdo->prepare('UPDATE v4_users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    /** Deactivating also ends all sessions of the user. */
    public function setActive(int $id, bool $active): void
    {
        $this->pdo->prepare(
            'UPDATE v4_users SET is_active = :active, session_epoch = session_epoch + :bump WHERE id = :id',
        )->execute(['active' => $active ? 1 : 0, 'bump' => $active ? 0 : 1, 'id' => $id]);
    }

    public function touchLogin(int $id): void
    {
        $this->pdo->prepare('UPDATE v4_users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id')->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM v4_users WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['username'],
            (bool) $row['is_active'],
            (int) $row['session_epoch'],
            new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')),
            $row['last_login_at'] === null ? null : new \DateTimeImmutable((string) $row['last_login_at'], new \DateTimeZone('UTC')),
            $row['created_by_name'] === null ? null : (string) $row['created_by_name'],
        );
    }
}
