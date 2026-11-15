<?php

declare(strict_types=1);

/*
 * Ranges are written low to high from 4.5 on ("455-456", docs/fachlogik.md,
 * section 2). Flips stored pairs in the work and live tables and the range in
 * the live texts; all other values keep the higher value first. The text is
 * changed before the value: MySQL evaluates SET from left to right.
 */

use Lab09\Disclaimer\Database\Migration;

return new class implements Migration {
    public function description(): string
    {
        return 'Ranges low to high';
    }

    public function up(PDO $pdo): void
    {
        $first = "SUBSTRING_INDEX(electric_range, '-', 1)";
        $second = "SUBSTRING_INDEX(electric_range, '-', -1)";
        $flipped = "CONCAT({$second}, '-', {$first})";
        $where = "electric_range REGEXP '^[0-9]+-[0-9]+$'
            AND CAST({$first} AS UNSIGNED) > CAST({$second} AS UNSIGNED)";

        $pdo->exec("UPDATE v4_vehicles SET electric_range = {$flipped} WHERE {$where}");
        $pdo->exec("UPDATE v4_vehicles_live SET
            disclaimer_text = REPLACE(disclaimer_text,
                CONCAT('electric range: ', electric_range, ' km'),
                CONCAT('electric range: ', {$flipped}, ' km')),
            electric_range = {$flipped}
            WHERE {$where}");
    }
};
