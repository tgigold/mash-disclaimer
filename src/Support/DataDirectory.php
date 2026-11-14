<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Support;

/**
 * The server-only directory data/ (config, setup code, logs, backups). It lives
 * next to public/, is never deployed or deleted by rsync (H-13) and blocks web
 * access itself in case the document root ever points at the project (G-1).
 */
final readonly class DataDirectory
{
    public function __construct(public string $path)
    {
    }

    public function file(string $relative): string
    {
        return $this->path . '/' . ltrim($relative, '/');
    }

    /**
     * Creates data/ or a subdirectory of it and makes sure data/.htaccess exists.
     * Returns the absolute path, or null if it cannot be created.
     */
    public function ensure(string $subdirectory = ''): ?string
    {
        $dir = $subdirectory === '' ? $this->path : $this->file($subdirectory);

        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return null;
        }

        $htaccess = $this->file('.htaccess');
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }

        return $dir;
    }

    public function isWritable(): bool
    {
        $dir = $this->ensure();

        return $dir !== null && is_writable($dir);
    }

    /**
     * Writes a file atomically (temp file + rename) with restrictive permissions,
     * so a crash never leaves half a config behind.
     */
    public function writeAtomically(string $relative, string $contents, int $mode = 0600): void
    {
        if ($this->ensure(dirname($relative) === '.' ? '' : dirname($relative)) === null) {
            throw new \RuntimeException('The data directory is not writable.');
        }

        $target = $this->file($relative);
        $temp = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (@file_put_contents($temp, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write ' . $relative . '.');
        }
        @chmod($temp, $mode);

        if (!@rename($temp, $target)) {
            @unlink($temp);
            throw new \RuntimeException('Could not write ' . $relative . '.');
        }
    }
}
