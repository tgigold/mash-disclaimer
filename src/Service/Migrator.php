<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Database\Migration;
use Lab09\Disclaimer\Repository\SchemaRepository;
use PDO;

/**
 * Runs the numbered migrations in migrations/ that have not been applied yet,
 * after a backup (G-12). Usable from the setup, the CLI and later the admin area.
 */
final readonly class Migrator
{
    public function __construct(
        private PDO $pdo,
        private SchemaRepository $schema,
        private BackupService $backup,
        private string $directory,
    ) {
    }

    /** @return list<string> versions in order, e.g. "0001_initial_schema" */
    public function available(): array
    {
        $versions = [];
        foreach (glob($this->directory . '/[0-9][0-9][0-9][0-9]_*.php') ?: [] as $file) {
            $versions[] = basename($file, '.php');
        }
        sort($versions, SORT_STRING);

        return $versions;
    }

    /** @return list<string> */
    public function pending(): array
    {
        return array_values(array_diff($this->available(), $this->schema->appliedMigrations()));
    }

    /**
     * @return list<string> the versions that were applied
     */
    public function migrate(): array
    {
        $pending = $this->pending();
        if ($pending === []) {
            return [];
        }

        $this->backup->create('before-migration');
        $this->schema->createMigrationsTable();

        foreach ($pending as $version) {
            $migration = $this->load($version);

            // Each migration runs in its own transaction. Caveat: MySQL commits
            // implicitly after every CREATE/ALTER/DROP, so a migration with DDL is
            // not atomic. That is why a backup is taken first and why migrations
            // are written so they can be re-run (CREATE TABLE IF NOT EXISTS …).
            $this->pdo->beginTransaction();
            try {
                $migration->up($this->pdo);
                $this->schema->recordMigration($version, $migration->description());
                if ($this->pdo->inTransaction()) {
                    $this->pdo->commit();
                }
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw new \RuntimeException(sprintf('Migration %s failed: %s', $version, $e->getMessage()), 0, $e);
            }
        }

        return $pending;
    }

    private function load(string $version): Migration
    {
        if (preg_match('/^\d{4}_[a-z0-9_]+$/', $version) !== 1) {
            throw new \InvalidArgumentException('Invalid migration name.');
        }

        $migration = require $this->directory . '/' . $version . '.php';
        if (!$migration instanceof Migration) {
            throw new \RuntimeException(sprintf('%s must return a Migration.', $version));
        }

        return $migration;
    }
}
