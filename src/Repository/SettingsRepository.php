<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use PDO;

/**
 * Day-to-day settings in v4_settings (G-13: they live in the app, not in the config).
 */
final readonly class SettingsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{value: string|null, updated_at: string, updated_by: int|null, updated_by_name: string|null}|null */
    public function get(string $name): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.value, s.updated_at, s.updated_by, u.username AS updated_by_name
             FROM v4_settings s LEFT JOIN v4_users u ON u.id = s.updated_by WHERE s.name = :name',
        );
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'value' => $row['value'] === null ? null : (string) $row['value'],
            'updated_at' => (string) $row['updated_at'],
            'updated_by' => $row['updated_by'] === null ? null : (int) $row['updated_by'],
            'updated_by_name' => $row['updated_by_name'] === null ? null : (string) $row['updated_by_name'],
        ];
    }

    public function set(string $name, ?string $value, ?int $userId): void
    {
        $this->pdo->prepare(
            'INSERT INTO v4_settings (name, value, updated_at, updated_by) VALUES (:name, :value, UTC_TIMESTAMP(), :user)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)',
        )->execute(['name' => $name, 'value' => $value, 'user' => $userId]);
    }

    public function delete(string $name): void
    {
        $this->pdo->prepare('DELETE FROM v4_settings WHERE name = :name')->execute(['name' => $name]);
    }
}
