<?php

declare(strict_types=1);

/*
 * Initial schema: settings, admin accounts, audit log, vehicles (work) and live
 * vehicles. Login attempts arrive with the login (step 4), imports and staging
 * with the import (step 6) – each as its own migration.
 *
 * Every statement is CREATE TABLE IF NOT EXISTS, so the migration can be re-run
 * after a partial failure (MySQL commits DDL implicitly, see Migrator).
 */

use Lab09\Disclaimer\Database\Migration;

return new class implements Migration {
    public function description(): string
    {
        return 'Initial schema: settings, users, audit log, vehicles, live vehicles';
    }

    public function up(PDO $pdo): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // Admin accounts. One role. session_epoch ends all sessions of a user
        // when it is increased, e.g. after a new password (S-10).
        $pdo->exec("CREATE TABLE IF NOT EXISTS v4_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            session_epoch INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            created_by INT UNSIGNED NULL,
            password_changed_at DATETIME NOT NULL,
            last_login_at DATETIME NULL,
            UNIQUE KEY uniq_username (username),
            CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES v4_users (id) ON DELETE SET NULL
        ) {$options}");

        // Day-to-day settings that live in the app, not in the config file (G-13),
        // e.g. the frontend lock during an import.
        $pdo->exec("CREATE TABLE IF NOT EXISTS v4_settings (
            name VARCHAR(64) NOT NULL PRIMARY KEY,
            value TEXT NULL,
            updated_at DATETIME NOT NULL,
            updated_by INT UNSIGNED NULL,
            CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES v4_users (id) ON DELETE SET NULL
        ) {$options}");

        // Who did what and when (S-39); feeds "Change history". ip is shortened (D-6).
        $pdo->exec("CREATE TABLE IF NOT EXISTS v4_audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL,
            user_id INT UNSIGNED NULL,
            action VARCHAR(64) NOT NULL,
            object_type VARCHAR(32) NULL,
            object_id VARCHAR(32) NULL,
            details TEXT NULL,
            ip VARCHAR(45) NULL,
            KEY idx_audit_created (created_at),
            KEY idx_audit_object (object_type, object_id),
            CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES v4_users (id) ON DELETE SET NULL
        ) {$options}");

        // Work table: one row per vehicle with the factory values (or, for
        // vehicles created by hand, the values entered). id is a random
        // 8-character Crockford Base32 code, type_code may be empty for manual
        // vehicles; several NULLs are allowed by the unique key.
        $pdo->exec("CREATE TABLE IF NOT EXISTS v4_vehicles (
            id CHAR(8) NOT NULL PRIMARY KEY,
            type_code VARCHAR(5) NULL,
            origin VARCHAR(8) NOT NULL,
            brand VARCHAR(16) NOT NULL,
            series VARCHAR(16) NULL,
            drivetrain VARCHAR(4) NOT NULL,
            name VARCHAR(255) NOT NULL,
            source_name VARCHAR(255) NULL,
            fuel_consumption VARCHAR(32) NULL,
            electric_consumption VARCHAR(32) NULL,
            co2_emissions VARCHAR(32) NULL,
            co2_class VARCHAR(8) NULL,
            co2_class_discharged VARCHAR(8) NULL,
            fuel_consumption_discharged VARCHAR(32) NULL,
            electric_range VARCHAR(32) NULL,
            source_created_on DATE NULL,
            created_at DATETIME NOT NULL,
            first_imported_at DATETIME NULL,
            last_imported_at DATETIME NULL,
            last_import_id INT UNSIGNED NULL,
            UNIQUE KEY uniq_type_code (type_code),
            KEY idx_vehicles_series (series),
            KEY idx_vehicles_brand (brand)
        ) {$options}");

        // Live table: only vehicles that are live. Holds the (editable) values
        // and the finished disclaimer text the search reads.
        $pdo->exec("CREATE TABLE IF NOT EXISTS v4_vehicles_live (
            vehicle_id CHAR(8) NOT NULL PRIMARY KEY,
            fuel_consumption VARCHAR(32) NULL,
            electric_consumption VARCHAR(32) NULL,
            co2_emissions VARCHAR(32) NULL,
            co2_class VARCHAR(8) NULL,
            co2_class_discharged VARCHAR(8) NULL,
            fuel_consumption_discharged VARCHAR(32) NULL,
            electric_range VARCHAR(32) NULL,
            disclaimer_text TEXT NOT NULL,
            is_edited TINYINT(1) NOT NULL DEFAULT 0,
            activated_at DATETIME NOT NULL,
            edited_at DATETIME NULL,
            edited_by INT UNSIGNED NULL,
            CONSTRAINT fk_live_vehicle FOREIGN KEY (vehicle_id) REFERENCES v4_vehicles (id) ON DELETE CASCADE,
            CONSTRAINT fk_live_edited_by FOREIGN KEY (edited_by) REFERENCES v4_users (id) ON DELETE SET NULL
        ) {$options}");
    }
};
