<?php

declare(strict_types=1);

/*
 * Import (docs/fachlogik.md, section 4): one row per uploaded file and run,
 * and the staging table with every type code read from it. Work and live
 * tables stay untouched until the person applies the import.
 */

use Lab09\Disclaimer\Database\Migration;

return new class implements Migration {
    public function description(): string
    {
        return 'Imports and staging of imported vehicles';
    }

    public function up(PDO $pdo): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // status: parsed (in review) → applied → released, or aborted.
        $pdo->exec("CREATE TABLE IF NOT EXISTS v4_imports (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(80) NOT NULL,
            sha256 CHAR(64) NOT NULL,
            size_bytes INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            uploaded_at DATETIME NOT NULL,
            started_at DATETIME NULL,
            applied_at DATETIME NULL,
            finished_at DATETIME NULL,
            status VARCHAR(16) NOT NULL,
            live_mode VARCHAR(16) NULL,
            counts TEXT NULL,
            KEY idx_imports_status (status),
            CONSTRAINT fk_imports_user FOREIGN KEY (user_id) REFERENCES v4_users (id) ON DELETE SET NULL
        ) {$options}");

        // classification: new, changed, unchanged, problem. vehicle_id points to
        // the existing work record for changed/unchanged ones.
        $pdo->exec("CREATE TABLE IF NOT EXISTS v4_import_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            import_id INT UNSIGNED NOT NULL,
            sheet VARCHAR(64) NOT NULL,
            column_ref VARCHAR(8) NOT NULL,
            type_code VARCHAR(5) NULL,
            brand VARCHAR(16) NULL,
            series VARCHAR(16) NULL,
            drivetrain VARCHAR(4) NULL,
            source_name VARCHAR(255) NOT NULL,
            item_values TEXT NOT NULL,
            raw_column MEDIUMTEXT NOT NULL,
            created_on DATE NULL,
            classification VARCHAR(12) NOT NULL,
            problems TEXT NULL,
            vehicle_id CHAR(8) NULL,
            KEY idx_items_import (import_id, classification),
            KEY idx_items_code (type_code),
            CONSTRAINT fk_items_import FOREIGN KEY (import_id) REFERENCES v4_imports (id) ON DELETE CASCADE
        ) {$options}");

        // Link work records to the import that last touched them. Added only
        // if missing, so the migration can be re-run.
        $exists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE table_schema = DATABASE() AND table_name = 'v4_vehicles' AND constraint_name = 'fk_vehicles_import'",
        );
        if ($exists !== false && (int) $exists->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE v4_vehicles ADD CONSTRAINT fk_vehicles_import
                FOREIGN KEY (last_import_id) REFERENCES v4_imports (id) ON DELETE SET NULL');
        }
    }
};
