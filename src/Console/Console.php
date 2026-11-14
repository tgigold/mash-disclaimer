<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Console;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Database\Database;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Service\BackupService;
use Lab09\Disclaimer\Service\DisclaimerBuilder;
use Lab09\Disclaimer\Service\Migrator;
use Lab09\Disclaimer\Service\SampleData;
use Lab09\Disclaimer\Support\DataDirectory;
use PDO;

/**
 * Command line tasks for operation via SSH (G-12: migrations also via CLI).
 * On the webspace call it with PHP 8.5: php85 bin/console <command>
 */
final class Console
{
    private const array COMMANDS = [
        'migrate' => 'Back up the database and run pending migrations',
        'migrate:status' => 'List applied and pending migrations',
        'backup' => 'Write a database backup to data/backups/',
        'sample-data:load' => 'Add the invented sample vehicles',
        'sample-data:remove' => 'Delete the invented sample vehicles',
    ];

    /** @var resource */
    private $out;

    /** @param resource|null $out */
    public function __construct(private readonly DataDirectory $data, $out = null)
    {
        $this->out = $out ?? STDOUT;
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';

        if (!array_key_exists($command, self::COMMANDS)) {
            $this->help();

            return $command === 'help' ? 0 : 1;
        }

        $config = Config::load($this->data->file('config.php'));
        $settings = $config->database();
        if ($settings === null) {
            $this->line('Not installed yet – run the setup in the browser first.');

            return 1;
        }

        $pdo = Database::connect($settings);
        $schema = new SchemaRepository($pdo);
        $backup = new BackupService($schema, $this->data);
        $migrator = new Migrator($pdo, $schema, $backup, App::rootPath('migrations'));

        switch ($command) {
            case 'migrate':
                $applied = $migrator->migrate();
                $this->line($applied === [] ? 'Nothing to do.' : 'Applied: ' . implode(', ', $applied));
                break;
            case 'migrate:status':
                $applied = $schema->appliedMigrations();
                foreach ($migrator->available() as $version) {
                    $this->line(sprintf('%s  %s', in_array($version, $applied, true) ? 'applied' : 'PENDING', $version));
                }
                break;
            case 'backup':
                $file = $backup->create('manual');
                $this->line($file === null ? 'No tables to back up.' : 'Written: ' . basename($file));
                break;
            case 'sample-data:load':
                $this->line(sprintf('Added %d sample vehicles.', $this->sampleData($pdo)->load()));
                break;
            case 'sample-data:remove':
                $this->line(sprintf('Removed %d sample vehicles.', $this->sampleData($pdo)->remove()));
                break;
        }

        return 0;
    }

    private function sampleData(PDO $pdo): SampleData
    {
        return new SampleData(
            $pdo,
            new VehicleRepository($pdo),
            new AuditLogRepository($pdo),
            DisclaimerBuilder::fromFile(App::rootPath('config/disclaimer-templates.php')),
        );
    }

    private function help(): void
    {
        $this->line('Usage: php85 bin/console <command>');
        $this->line('');
        foreach (self::COMMANDS as $name => $description) {
            $this->line(sprintf('  %-20s %s', $name, $description));
        }
    }

    private function line(string $text): void
    {
        fwrite($this->out, $text . PHP_EOL);
    }
}
