<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use PDO;

/**
 * Failed login attempts and known devices for the login lock (S-31).
 */
final readonly class LoginProtectionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function recordFailure(string $ip): void
    {
        $this->pdo->prepare('INSERT INTO v4_login_attempts (attempted_at, ip) VALUES (UTC_TIMESTAMP(), :ip)')
            ->execute(['ip' => $ip]);
    }

    public function failuresForIp(string $ip, int $windowSeconds): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM v4_login_attempts WHERE ip = :ip AND attempted_at > UTC_TIMESTAMP() - INTERVAL :window SECOND',
        );
        $stmt->bindValue('ip', $ip);
        $stmt->bindValue('window', $windowSeconds, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function failuresTotal(int $windowSeconds): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM v4_login_attempts WHERE attempted_at > UTC_TIMESTAMP() - INTERVAL :window SECOND',
        );
        $stmt->bindValue('window', $windowSeconds, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** Seconds until the oldest relevant failure of this IP leaves the window. */
    public function secondsUntilIpUnlocked(string $ip, int $windowSeconds): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), MIN(attempted_at) + INTERVAL :window SECOND)
             FROM v4_login_attempts WHERE ip = :ip AND attempted_at > UTC_TIMESTAMP() - INTERVAL :window2 SECOND',
        );
        $stmt->bindValue('window', $windowSeconds, PDO::PARAM_INT);
        $stmt->bindValue('window2', $windowSeconds, PDO::PARAM_INT);
        $stmt->bindValue('ip', $ip);
        $stmt->execute();

        return max(1, (int) $stmt->fetchColumn());
    }

    /** Full IPs live only as long as a lock can last (D-6). */
    public function pruneAttempts(int $olderThanSeconds): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM v4_login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL :age SECOND');
        $stmt->bindValue('age', $olderThanSeconds, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function rememberDevice(string $tokenHash, int $userId): void
    {
        $this->pdo->prepare(
            'INSERT INTO v4_known_devices (token_hash, user_id, created_at, last_used_at)
             VALUES (:hash, :user, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE last_used_at = UTC_TIMESTAMP(), user_id = VALUES(user_id)',
        )->execute(['hash' => $tokenHash, 'user' => $userId]);
    }

    public function isKnownDevice(string $tokenHash): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM v4_known_devices d JOIN v4_users u ON u.id = d.user_id
             WHERE d.token_hash = :hash AND u.is_active = 1',
        );
        $stmt->execute(['hash' => $tokenHash]);

        return $stmt->fetchColumn() !== false;
    }

    public function pruneDevices(int $unusedForSeconds): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM v4_known_devices WHERE last_used_at < UTC_TIMESTAMP() - INTERVAL :age SECOND');
        $stmt->bindValue('age', $unusedForSeconds, PDO::PARAM_INT);
        $stmt->execute();
    }
}
