<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Repository;

use Lab09\Disclaimer\Database\Database;
use PDO;

/**
 * Database-level queries: which tables exist, which migrations ran, and the raw
 * material for backups.
 */
final readonly class SchemaRepository
{
    private const string MIGRATIONS = Database::PREFIX . 'migrations';

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<string> tables of this project, i.e. with the v4_ prefix */
    public function projectTables(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT table_name FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_type = :type AND table_name LIKE :prefix
             ORDER BY table_name',
        );
        // S-18: the underscore in the prefix is a LIKE wildcard and must be escaped.
        $stmt->execute(['type' => 'BASE TABLE', 'prefix' => str_replace('_', '\\_', Database::PREFIX) . '%']);

        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function createMigrationsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::MIGRATIONS . ' (
                version VARCHAR(64) NOT NULL PRIMARY KEY,
                description VARCHAR(255) NOT NULL,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /** @return list<string> */
    public function appliedMigrations(): array
    {
        if (!in_array(self::MIGRATIONS, $this->projectTables(), true)) {
            return [];
        }

        $stmt = $this->pdo->query('SELECT version FROM ' . self::MIGRATIONS . ' ORDER BY version');

        return $stmt === false ? [] : array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function recordMigration(string $version, string $description): void
    {
        $this->pdo->prepare(
            'INSERT INTO ' . self::MIGRATIONS . ' (version, description, applied_at) VALUES (:version, :description, UTC_TIMESTAMP())',
        )->execute(['version' => $version, 'description' => $description]);
    }

    /** Table names come from projectTables() only, never from a request (S-17). */
    public function createStatement(string $table): string
    {
        $this->assertProjectTable($table);
        $stmt = $this->pdo->query('SHOW CREATE TABLE `' . $table . '`');
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_NUM);

        if (!is_array($row) || !isset($row[1]) || !is_string($row[1])) {
            throw new \RuntimeException('Could not read the structure of ' . $table . '.');
        }

        return $row[1];
    }

    /** @return \Generator<int, array<string, mixed>> */
    public function rows(string $table): \Generator
    {
        $this->assertProjectTable($table);
        $stmt = $this->pdo->query('SELECT * FROM `' . $table . '`');
        if ($stmt === false) {
            return;
        }

        while (is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            /** @var array<string, mixed> $row */
            yield $row;
        }
    }

    public function quote(string $value): string
    {
        return $this->pdo->quote($value);
    }

    private function assertProjectTable(string $table): void
    {
        if (preg_match('/^' . preg_quote(Database::PREFIX, '/') . '[a-z0-9_]+$/', $table) !== 1) {
            throw new \InvalidArgumentException('Not a table of this project.');
        }
    }
}
