<?php

declare(strict_types=1);

namespace Lab09\Disclaimer;

/**
 * Holds facts about the application itself – above all the version, which lives
 * nowhere else (G-5).
 */
final class App
{
    public const string NAME = 'Disclaimer Tool';

    public const string VERSION = '4.5.0';

    /** Project root, i.e. the directory above public/. */
    public static function rootPath(string $relative = ''): string
    {
        $root = dirname(__DIR__);

        return $relative === '' ? $root : $root . '/' . ltrim($relative, '/');
    }
}
