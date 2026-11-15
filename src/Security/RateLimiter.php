<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Security;

use Lab09\Disclaimer\Support\DataDirectory;

/**
 * Fixed-window rate limit per minute (API-7), kept in small files under
 * data/ratelimit/ – no database table, so it also works before the setup and
 * needs no migration. The key is a hash of the shortened IP (D-6) and the
 * route; files older than the current minute are deleted on the fly.
 */
final readonly class RateLimiter
{
    public function __construct(private DataDirectory $data, private ?\Closure $clock = null)
    {
    }

    /**
     * Counts this request and says whether it is still within the limit.
     *
     * @return int|null null if allowed, otherwise seconds until the next window
     */
    public function hit(string $shortenedIp, string $bucket, int $perMinute): ?int
    {
        $now = $this->clock !== null ? (int) ($this->clock)() : time();
        $window = intdiv($now, 60);

        $dir = $this->data->ensure('ratelimit/' . $window);
        if ($dir === null) {
            // Fail open: a full disk must not take the public search down.
            return null;
        }
        $this->prune($window);

        $file = $dir . '/' . hash('sha256', $shortenedIp . '|' . $bucket);
        // One byte per request; the file size is the counter.
        @file_put_contents($file, '.', FILE_APPEND | LOCK_EX);
        clearstatcache(true, $file);
        $count = (int) @filesize($file);

        return $count > $perMinute ? 60 - ($now % 60) : null;
    }

    private function prune(int $currentWindow): void
    {
        foreach (glob($this->data->file('ratelimit') . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if ((int) basename($dir) >= $currentWindow - 1) {
                continue;
            }
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
}
