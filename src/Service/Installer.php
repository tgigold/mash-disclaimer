<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Service;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Database\Database;
use Lab09\Disclaimer\Database\DatabaseSettings;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Security\SetupCode;
use Lab09\Disclaimer\Support\DataDirectory;
use PDO;

/**
 * The guided setup (G-11): checks the server, connects to the database, runs the
 * migrations, creates the first admin, optionally loads sample vehicles and
 * finally writes data/config.php. The config is written last – it marks the
 * installation as complete and locks the setup route.
 */
final readonly class Installer
{
    public const string CONFIG_FILE = 'config.php';

    /** @param \Closure(DatabaseSettings): PDO|null $connect */
    public function __construct(
        private DataDirectory $data,
        private SetupCode $setupCode,
        private ?\Closure $connect = null,
        private int $phpVersionId = PHP_VERSION_ID,
    ) {
    }

    /**
     * What the server is missing, in plain words. Empty means ready.
     *
     * @return list<string>
     */
    public function missingRequirements(): array
    {
        $missing = [];
        if ($this->phpVersionId < 80500) {
            $missing[] = 'PHP 8.5 or newer (this server runs ' . PHP_VERSION . ').';
        }
        // pdo_mysql for the database; zip, xmlreader and gd for reading the XLSX (PhpSpreadsheet).
        foreach (['pdo_mysql', 'mbstring', 'zlib', 'intl', 'zip', 'xmlreader', 'gd', 'fileinfo'] as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = 'The PHP extension ' . $extension . '.';
            }
        }
        if (!defined('PASSWORD_ARGON2ID')) {
            $missing[] = 'Argon2id password hashing (PHP built with libargon2 or sodium).';
        }
        if (!$this->data->isWritable()) {
            $missing[] = 'A writable directory data/ next to public/.';
        }

        return $missing;
    }

    public function install(SetupInput $input, ?string $shortenedIp = null): void
    {
        $pdo = $this->connect($input->database);

        $schema = new SchemaRepository($pdo);
        $migrator = new Migrator($pdo, $schema, new BackupService($schema, $this->data), App::rootPath('migrations'));
        try {
            $migrator->migrate();
        } catch (\RuntimeException $e) {
            throw new SetupException($e->getMessage() . ' Does the database user have the right to create tables?', 0, $e);
        }

        $users = new UserRepository($pdo);
        if ($users->count() > 0) {
            throw new SetupException(
                'This database already contains admin accounts of the Disclaimer Tool. '
                . 'Use an empty database, or restore data/config.php if this is an existing installation.',
            );
        }

        $audit = new AuditLogRepository($pdo);

        // Sample data first: it skips vehicles that already exist, so a setup
        // that failed later can simply be submitted again.
        if ($input->sampleData) {
            $sample = new SampleData(
                $pdo,
                new VehicleRepository($pdo),
                $audit,
                DisclaimerBuilder::fromFile(App::rootPath('config/disclaimer-templates.php')),
            );
            $sample->load();
        }

        $pdo->beginTransaction();
        try {
            $userId = $users->create($input->adminUsername, password_hash($input->adminPassword, PASSWORD_ARGON2ID), null);
            $audit->add($userId, 'setup.completed', details: ['version' => App::VERSION], shortenedIp: $shortenedIp);
            $audit->add($userId, 'user.created', 'user', (string) $userId, ['username' => $input->adminUsername], $shortenedIp);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $this->data->writeAtomically(self::CONFIG_FILE, Config::export([
            'base_url' => $input->baseUrl,
            'trusted_proxies' => [],
            'db' => $input->database->toArray(),
        ]));

        $this->setupCode->remove();
    }

    private function connect(DatabaseSettings $settings): PDO
    {
        try {
            return $this->connect !== null ? ($this->connect)($settings) : Database::connect($settings);
        } catch (\PDOException $e) {
            // The driver message names host and user, never the password.
            throw new SetupException('Could not connect to the database: ' . $e->getMessage(), 0, $e);
        }
    }
}
