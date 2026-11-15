<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\LoginProtectionRepository;

/**
 * Deletes personal data after its retention period (D-6, D-7). There are no
 * cron jobs, so this runs on every login page and login – cheap DELETEs on
 * indexed columns.
 */
final readonly class RetentionService
{
    public const int AUDIT_DAYS = 730;

    public const int AUDIT_IP_DAYS = 90;

    public function __construct(
        private AuditLogRepository $audit,
        private LoginProtectionRepository $protection,
    ) {
    }

    public function run(): void
    {
        $this->protection->pruneAttempts(AuthService::LOCK_SECONDS);
        $this->protection->pruneDevices(AuthService::DEVICE_LIFETIME_SECONDS);
        $this->audit->forgetIpsOlderThan(self::AUDIT_IP_DAYS);
        $this->audit->deleteOlderThan(self::AUDIT_DAYS);
    }
}
