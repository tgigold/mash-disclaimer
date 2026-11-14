<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Database;

use PDO;

/**
 * Opens the MySQL/MariaDB connection with the settings the rules demand:
 * exceptions, real prepared statements (G-17), utf8mb4 and strict mode (G-16),
 * UTC as session time zone so DATETIME values mean the same everywhere.
 */
final class Database
{
    /** All tables of this project carry this prefix; it is fixed, not configurable. */
    public const string PREFIX = 'v4_';

    private const string SQL_MODE = 'STRICT_ALL_TABLES,ONLY_FULL_GROUP_BY,NO_ZERO_IN_DATE,NO_ZERO_DATE,'
        . 'ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

    public static function connect(DatabaseSettings $settings): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $settings->host,
            $settings->port,
            $settings->name,
        );

        return new PDO($dsn, $settings->user, $settings->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
            \Pdo\Mysql::ATTR_INIT_COMMAND => sprintf(
                "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, SESSION sql_mode = '%s', time_zone = '+00:00'",
                self::SQL_MODE,
            ),
        ]);
    }
}
