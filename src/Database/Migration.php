<?php

declare(strict_types=1);

namespace Lab09\Disclaimer\Database;

use PDO;

/**
 * One numbered database change in migrations/. Migrations are the only place
 * besides repositories that contain SQL. An applied migration is never edited;
 * a change always gets a new one (G-12).
 */
interface Migration
{
    /** One sentence for the log and the update page. */
    public function description(): string;

    public function up(PDO $pdo): void;
}
