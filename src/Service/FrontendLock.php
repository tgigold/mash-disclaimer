<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\User;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\SettingsRepository;

/**
 * Locks the public search while an import runs, so nobody copies a disclaimer
 * that is about to change. The lock starts with the import and is released by
 * hand, by any admin (docs/fachlogik.md, section 4).
 */
final readonly class FrontendLock
{
    private const string SETTING = 'frontend_lock';

    public function __construct(
        private SettingsRepository $settings,
        private AuditLogRepository $audit,
    ) {
    }

    /** @return array{since: \DateTimeImmutable, by: string|null, import_id: int|null}|null */
    public function status(): ?array
    {
        $row = $this->settings->get(self::SETTING);
        if ($row === null) {
            return null;
        }
        $importId = is_numeric($row['value']) ? (int) $row['value'] : null;

        return [
            'since' => new \DateTimeImmutable($row['updated_at'], new \DateTimeZone('UTC')),
            'by' => $row['updated_by_name'],
            'import_id' => $importId,
        ];
    }

    public function isLocked(): bool
    {
        return $this->settings->get(self::SETTING) !== null;
    }

    public function lock(User $actor, int $importId, ?string $ip): void
    {
        if ($this->isLocked()) {
            return;
        }
        $this->settings->set(self::SETTING, (string) $importId, $actor->id);
        $this->audit->add($actor->id, 'frontend.locked', 'import', (string) $importId, shortenedIp: $ip);
    }

    public function release(User $actor, ?string $ip): void
    {
        $status = $this->status();
        if ($status === null) {
            return;
        }
        $this->settings->delete(self::SETTING);
        $this->audit->add($actor->id, 'frontend.released', 'import', $status['import_id'] === null ? null : (string) $status['import_id'], shortenedIp: $ip);
    }
}
