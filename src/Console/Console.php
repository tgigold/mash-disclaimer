<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Console;

use Lab09\Disclaimer\App;
use Lab09\Disclaimer\Config;
use Lab09\Disclaimer\Database\Database;
use Lab09\Disclaimer\Repository\AuditLogRepository;
use Lab09\Disclaimer\Repository\SchemaRepository;
use Lab09\Disclaimer\Repository\UserRepository;
use Lab09\Disclaimer\Repository\VehicleRepository;
use Lab09\Disclaimer\Service\BackupService;
use Lab09\Disclaimer\Service\DisclaimerBuilder;
use Lab09\Disclaimer\Service\Migrator;
use Lab09\Disclaimer\Service\PasswordPolicy;
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
        'user:create' => 'Add an admin (emergency access): user:create <username>, password via standard input',
    ];

    /** @var resource */
    private $out;

    /** @var resource */
    private $in;

    /**
     * @param resource|null $out
     * @param resource|null $in
     */
    public function __construct(private readonly DataDirectory $data, $out = null, $in = null)
    {
        $this->out = $out ?? STDOUT;
        $this->in = $in ?? STDIN;
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
            case 'user:create':
                return $this->createUser($pdo, $argv[2] ?? '');
        }

        return 0;
    }

    /**
     * Emergency access when no admin can sign in any more. Whoever runs this
     * already has access to the server. The password is read from standard
     * input, so it appears neither in the process list nor in the shell history.
     */
    private function createUser(PDO $pdo, string $username): int
    {
        if (preg_match(PasswordPolicy::USERNAME_PATTERN, $username) !== 1) {
            $this->line('Usage: php85 bin/console user:create <username>   (3–64 letters, digits, . _ -)');

            return 1;
        }
        $users = new UserRepository($pdo);
        if ($users->findByUsername($username) !== null) {
            $this->line('This username is taken.');

            return 1;
        }

        if (stream_isatty($this->in)) {
            fwrite($this->out, 'Password (input hidden): ');
            shell_exec('stty -echo');
        }
        $password = rtrim((string) fgets($this->in), "\r\n");
        if (stream_isatty($this->in)) {
            shell_exec('stty echo');
            fwrite($this->out, PHP_EOL);
        }

        $problem = PasswordPolicy::problem($password);
        if ($problem !== null) {
            $this->line('Password: ' . $problem);

            return 1;
        }

        $id = $users->create($username, PasswordPolicy::hash($password), null);
        (new AuditLogRepository($pdo))->add(null, 'user.created', 'user', (string) $id, ['username' => $username, 'via' => 'command line']);
        $this->line(sprintf('Admin "%s" created.', $username));

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
