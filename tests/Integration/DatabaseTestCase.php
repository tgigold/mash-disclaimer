<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Tests\Integration;

use Lab09\Disclaimer\Database\Database;
use Lab09\Disclaimer\Database\DatabaseSettings;
use Lab09\Disclaimer\Repository\SchemaRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests against a real MariaDB/MySQL. They need a throwaway test
 * database (Q-4), given via TATER_TEST_DB_* environment variables; the CI
 * provides one in a container. Without it the tests are skipped locally – the
 * CI runs with --fail-on-skipped, so they cannot be skipped there.
 * Every test starts with an empty database.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected DatabaseSettings $settings;

    protected function setUp(): void
    {
        $host = getenv('TATER_TEST_DB_HOST');
        if (!is_string($host) || $host === '') {
            self::markTestSkipped('No test database configured (TATER_TEST_DB_HOST).');
        }

        $this->settings = new DatabaseSettings(
            $host,
            (int) (getenv('TATER_TEST_DB_PORT') ?: 3306),
            (string) getenv('TATER_TEST_DB_NAME'),
            (string) getenv('TATER_TEST_DB_USER'),
            (string) getenv('TATER_TEST_DB_PASSWORD'),
        );
        $this->pdo = Database::connect($this->settings);
        $this->dropProjectTables();
    }

    protected function dropProjectTables(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ((new SchemaRepository($this->pdo))->projectTables() as $table) {
            $this->pdo->exec('DROP TABLE `' . $table . '`');
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function countRows(string $sql): int
    {
        $stmt = $this->pdo->query($sql);

        return $stmt === false ? -1 : (int) $stmt->fetchColumn();
    }
}
