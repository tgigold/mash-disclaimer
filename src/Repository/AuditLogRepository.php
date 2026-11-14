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
}
