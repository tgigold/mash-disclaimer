<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Integration;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Service\BackupService;
use Lab09\Disclaimer\Service\Migrator;
use Lab09\Disclaimer\Support\DataDirectory;

final class MigratorTest extends DatabaseTestCase
{
    private DataDirectory $data;

    private string $migrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->data = new DataDirectory(sys_get_temp_dir() . '/tater-test-' . bin2hex(random_bytes(6)));
        $this->migrations = $this->data->path . '-migrations';
        mkdir($this->migrations, 0700, true);
        foreach (glob(App::rootPath('migrations/*.php')) ?: [] as $file) {
            copy($file, $this->migrations . '/' . basename($file));
        }
    }

    protected function tearDown(): void
    {
        if (!isset($this->data)) {
            return;
        }
        foreach ([$this->migrations, $this->data->path . '/backups', $this->data->path] as $dir) {
            foreach (glob($dir . '/{,.}[!.]*', GLOB_BRACE) ?: [] as $file) {
                is_file($file) && unlink($file);
            }
            is_dir($dir) && rmdir($dir);
        }
    }

    public function testFreshDatabaseGetsTheFullSchema(): void
    {
        $applied = $this->migrator()->migrate();

        self::assertSame(['0001_initial_schema', '0002_login_protection'], $applied);
        self::assertSame(
            ['v4_audit_log', 'v4_known_devices', 'v4_login_attempts', 'v4_migrations', 'v4_settings', 'v4_users', 'v4_vehicles', 'v4_vehicles_live'],
            (new SchemaRepository($this->pdo))->projectTables(),
        );
        self::assertSame([], $this->migrator()->pending());
    }

    public function testTablesUseUtf8mb4AndInnoDb(): void
    {
        $this->migrator()->migrate();

        $stmt = $this->pdo->query(
            "SELECT table_name, engine, table_collation FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name LIKE 'v4\\_%'",
        );
        self::assertNotFalse($stmt);
        foreach ($stmt->fetchAll() as $row) {
            $row = array_change_key_case($row, CASE_LOWER);
            self::assertSame('InnoDB', $row['engine'], (string) $row['table_name']);
            self::assertStringStartsWith('utf8mb4_', (string) $row['table_collation'], (string) $row['table_name']);
        }
    }

    public function testSecondRunDoesNothing(): void
    {
        $this->migrator()->migrate();

        self::assertSame([], $this->migrator()->migrate());
        self::assertSame(2, $this->countRows('SELECT COUNT(*) FROM v4_migrations'));
    }

    public function testBackupIsWrittenBeforeANewMigration(): void
    {
        $this->migrator()->migrate();
        $this->pdo->exec("INSERT INTO v4_settings (name, value, updated_at) VALUES ('probe', 'it''s here', UTC_TIMESTAMP())");
        file_put_contents($this->migrations . '/0099_probe.php', <<<'PHP'
            <?php
            return new class implements Lab09\Disclaimer\Database\Migration {
                public function description(): string { return 'Probe'; }
                public function up(PDO $pdo): void { $pdo->exec('CREATE TABLE IF NOT EXISTS v4_probe (id INT PRIMARY KEY) ENGINE=InnoDB'); }
            };
            PHP);

        self::assertSame(['0099_probe'], $this->migrator()->migrate());

        $backups = glob($this->data->path . '/backups/*-before-migration.sql.gz') ?: [];
        self::assertCount(1, $backups);
        $dump = (string) gzdecode((string) file_get_contents($backups[0]));
        self::assertStringContainsString('CREATE TABLE `v4_settings`', $dump);
        self::assertStringContainsString("'it\\'s here'", $dump);
        self::assertStringNotContainsString('v4_probe', $dump, 'The backup is taken before the migration.');
    }

    public function testFailingMigrationStopsWithItsName(): void
    {
        $this->migrator()->migrate();
        file_put_contents($this->migrations . '/0099_broken.php', <<<'PHP'
            <?php
            return new class implements Lab09\Disclaimer\Database\Migration {
                public function description(): string { return 'Broken'; }
                public function up(PDO $pdo): void { $pdo->exec('INSERT INTO v4_does_not_exist VALUES (1)'); }
            };
            PHP);

        try {
            $this->migrator()->migrate();
            self::fail('The migration should have failed.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Migration 0099_broken failed', $e->getMessage());
        }

        self::assertSame(['0099_broken'], $this->migrator()->pending());
    }

    private function migrator(): Migrator
    {
        $schema = new SchemaRepository($this->pdo);

        return new Migrator($this->pdo, $schema, new BackupService($schema, $this->data), $this->migrations);
    }
}
