<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Support\DataDirectory;

/**
 * Writes a gzip-compressed SQL dump of all v4_ tables to data/backups/. Runs
 * before every migration (G-12) and later before every import. Plain PHP instead
 * of mysqldump, because shared webspace does not guarantee the binary.
 */
final readonly class BackupService
{
    /** Older backups are deleted so the webspace cannot fill up (S-51). */
    private const int KEEP = 30;

    public function __construct(
        private SchemaRepository $schema,
        private DataDirectory $data,
    ) {
    }

    /** @return string|null path of the backup, or null if there was nothing to back up */
    public function create(string $reason): ?string
    {
        $tables = $this->schema->projectTables();
        if ($tables === []) {
            return null;
        }

        $dir = $this->data->ensure('backups');
        if ($dir === null) {
            throw new \RuntimeException('The backup directory is not writable.');
        }

        $reason = preg_replace('/[^a-z0-9-]+/', '-', strtolower($reason)) ?? 'backup';
        $file = sprintf('%s/%s-%s.sql.gz', $dir, gmdate('Ymd-His'), trim($reason, '-'));

        $gz = gzopen($file, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException('Could not create the backup file.');
        }

        try {
            gzwrite($gz, sprintf("-- Disclaimer Tool backup, %s UTC, reason: %s\nSET FOREIGN_KEY_CHECKS = 0;\n\n", gmdate('Y-m-d H:i:s'), $reason));
            foreach ($tables as $table) {
                gzwrite($gz, sprintf("DROP TABLE IF EXISTS `%s`;\n%s;\n\n", $table, $this->schema->createStatement($table)));
                foreach ($this->schema->rows($table) as $row) {
                    gzwrite($gz, $this->insert($table, $row));
                }
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
        } finally {
            gzclose($gz);
        }

        @chmod($file, 0600);
        $this->prune($dir);

        return $file;
    }

    /** @param array<string, mixed> $row */
    private function insert(string $table, array $row): string
    {
        $values = array_map(fn (mixed $value): string => match (true) {
            $value === null => 'NULL',
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => $this->schema->quote(is_scalar($value) ? (string) $value : ''),
        }, array_values($row));

        return sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s);\n",
            $table,
            implode('`, `', array_keys($row)),
            implode(', ', $values),
        );
    }

    private function prune(string $dir): void
    {
        $files = glob($dir . '/*.sql.gz') ?: [];
        sort($files);
        foreach (array_slice($files, 0, max(0, count($files) - self::KEEP)) as $old) {
            @unlink($old);
        }
    }
}
