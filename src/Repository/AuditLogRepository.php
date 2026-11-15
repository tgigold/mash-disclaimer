<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use PDO;

/**
 * Audit log in v4_audit_log (S-39): who did what, when. Details are JSON and
 * never contain passwords or tokens (S-49).
 */
final readonly class AuditLogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string, scalar|null> $details */
    public function add(
        ?int $userId,
        string $action,
        ?string $objectType = null,
        ?string $objectId = null,
        array $details = [],
        ?string $shortenedIp = null,
    ): void {
        $this->pdo->prepare(
            'INSERT INTO v4_audit_log (created_at, user_id, action, object_type, object_id, details, ip)
             VALUES (UTC_TIMESTAMP(), :user_id, :action, :object_type, :object_id, :details, :ip)',
        )->execute([
            'user_id' => $userId,
            'action' => $action,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'details' => $details === [] ? null : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip' => $shortenedIp,
        ]);
    }

    /** @return list<array{action: string, user_id: int|null, object_id: string|null}> newest first */
    public function latest(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT action, user_id, object_id FROM v4_audit_log ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue('limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'action' => (string) $row['action'],
                'user_id' => $row['user_id'] === null ? null : (int) $row['user_id'],
                'object_id' => $row['object_id'] === null ? null : (string) $row['object_id'],
            ];
        }

        return $rows;
    }

    /**
     * One page of the change history, newest first.
     *
     * @return list<array{created_at: string, username: string|null, action: string, object_type: string|null, object_id: string|null, details: array<string, mixed>}>
     */
    public function page(int $page, int $perPage, ?int $userId = null): array
    {
        $sql = 'SELECT a.created_at, u.username, a.action, a.object_type, a.object_id, a.details
                FROM v4_audit_log a LEFT JOIN v4_users u ON u.id = a.user_id';
        if ($userId !== null) {
            $sql .= ' WHERE a.user_id = :user';
        }
        $sql .= ' ORDER BY a.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        if ($userId !== null) {
            $stmt->bindValue('user', $userId, PDO::PARAM_INT);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $details = is_string($row['details']) ? json_decode($row['details'], true) : null;
            $rows[] = [
                'created_at' => (string) $row['created_at'],
                'username' => $row['username'] === null ? null : (string) $row['username'],
                'action' => (string) $row['action'],
                'object_type' => $row['object_type'] === null ? null : (string) $row['object_type'],
                'object_id' => $row['object_id'] === null ? null : (string) $row['object_id'],
                'details' => is_array($details) ? $details : [],
            ];
        }

        return $rows;
    }

    public function count(?int $userId = null): int
    {
        if ($userId === null) {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM v4_audit_log');

            return $stmt === false ? 0 : (int) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM v4_audit_log WHERE user_id = :user');
        $stmt->execute(['user' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /** D-7: entries older than the retention period are deleted. */
    public function deleteOlderThan(int $days): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM v4_audit_log WHERE created_at < UTC_TIMESTAMP() - INTERVAL :days DAY');
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();
    }

    /** D-7: shortened IPs are removed earlier than the entries themselves. */
    public function forgetIpsOlderThan(int $days): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE v4_audit_log SET ip = NULL WHERE ip IS NOT NULL AND created_at < UTC_TIMESTAMP() - INTERVAL :days DAY',
        );
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();
    }
}
