<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Support;

/**
 * Writes errors to data/logs/error.log – readable via SFTP, blocked for the web
 * (S-50). The file is rotated at a fixed size so a loop cannot fill the
 * webspace (S-51). Callers pass messages only, never request bodies or secrets (S-49).
 */
final readonly class ErrorLog
{
    private const int MAX_BYTES = 1_048_576;

    public function __construct(private string $directory)
    {
    }

    public function exception(\Throwable $e): void
    {
        $this->write(sprintf(
            '%s: %s in %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        ));
    }

    public function write(string $message): void
    {
        if (!$this->ensureDirectory()) {
            error_log($message);

            return;
        }

        $file = $this->directory . '/error.log';
        if (is_file($file) && filesize($file) > self::MAX_BYTES) {
            @rename($file, $file . '.1');
        }

        $line = sprintf("[%s] %s\n", gmdate('Y-m-d\TH:i:s\Z'), str_replace(["\r", "\n"], ' ', $message));
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private function ensureDirectory(): bool
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            return false;
        }

        // Second line of defence next to G-1, in case the document root ever
        // points at the project directory.
        $htaccess = dirname($this->directory) . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }

        return true;
    }
}
