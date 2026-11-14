<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Support;

use Lab09\Disclaimer\App;

/**
 * Builds asset URLs that carry the app version (A-6) and knows the integrity
 * hashes of third-party files (T-7). A test recomputes the hashes, so an
 * updated or altered vendor file fails the CI.
 */
final class Assets
{
    /** @var array<string, string> path below public/assets => SRI hash */
    public const array INTEGRITY = [
        'vendor/bootstrap-5.3.9/bootstrap.min.css'
            => 'sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX',
        'vendor/bootstrap-icons-1.13.2/bootstrap-icons.min.css'
            => 'sha384-nWN3KqaaVcbEsCjLSV+2jMjh62Iny/+mN3srM8TZeM8N6aj7eDl5wwYggjAJ4/sJ',
    ];

    public static function url(string $path): string
    {
        return '/assets/' . ltrim($path, '/') . '?v=' . rawurlencode(App::VERSION);
    }

    public static function integrity(string $path): ?string
    {
        return self::INTEGRITY[ltrim($path, '/')] ?? null;
    }
}
