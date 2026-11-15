<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Domain\User;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\SchemaRepository;

/**
 * The "Updates" page: pending migrations, run in the browser by an admin (G-12).
 * Deployments never run them on their own (H-15).
 */
final readonly class UpdateService
{
    public function __construct(
        private Migrator $migrator,
        private SchemaRepository $schema,
        private AuditLogRepository $audit,
    ) {
    }

    /** @return array{applied: list<string>, pending: list<string>} */
    public function status(): array
    {
        return ['applied' => $this->schema->appliedMigrations(), 'pending' => $this->migrator->pending()];
    }

    /** @return list<string> applied versions */
    public function migrate(User $actor, ?string $ip): array
    {
        $applied = $this->migrator->migrate();
        if ($applied !== []) {
            $this->audit->add($actor->id, 'migrations.applied', details: ['versions' => implode(', ', $applied)], shortenedIp: $ip);
        }

        return $applied;
    }
}
