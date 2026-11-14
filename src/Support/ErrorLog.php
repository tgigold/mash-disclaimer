<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Support;

/**
 * Writes errors and security events to data/logs/error.log – readable via SFTP,
 * blocked for the web (S-50). The file is rotated at a fixed size so a loop
 * cannot fill the webspace (S-51). Callers pass messages only, never request
 * bodies, passwords, tokens or setup codes (S-49).
 */
final readonly class ErrorLog
{
    private const int MAX_BYTES = 1_048_576;

    public function __construct(private DataDirectory $data)
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

    /** S-52: failed logins, rejected CSRF tokens, wrong setup codes … */
    public function security(string $event, string $shortenedIp): void
    {
        $this->write(sprintf('security: %s from %s', $event, $shortenedIp));
    }

    public function write(string $message): void
    {
        $dir = $this->data->ensure('logs');
        if ($dir === null) {
            error_log($message);

            return;
        }

        $file = $dir . '/error.log';
        if (is_file($file) && filesize($file) > self::MAX_BYTES) {
            @rename($file, $file . '.1');
        }

        $line = sprintf("[%s] %s\n", gmdate('Y-m-d\TH:i:s\Z'), str_replace(["\r", "\n"], ' ', $message));
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
