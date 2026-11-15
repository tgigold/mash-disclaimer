<?php

declare(strict_types=1);

/*
 * Login protection (S-31): failed attempts and devices that already signed in
 * successfully. Full IP addresses are kept only as long as a lock can last
 * (15 minutes, D-6); RetentionService deletes older rows.
 */

use Lab09\Disclaimer\Database\Migration;

return new class implements Migration {
    public function description(): string
    {
        return 'Login attempts and known devices';
    }

    public function up(PDO $pdo): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS v4_login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            attempted_at DATETIME NOT NULL,
            ip VARCHAR(45) NOT NULL,
            KEY idx_attempts_time (attempted_at),
            KEY idx_attempts_ip (ip, attempted_at)
        ) {$options}");

        // A device that signed in once is exempt from the global lock, so an
        // attacker cannot lock the owners out. Only a hash of the cookie is stored.
        $pdo->exec("CREATE TABLE IF NOT EXISTS v4_known_devices (
            token_hash CHAR(64) NOT NULL PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME NOT NULL,
            KEY idx_devices_used (last_used_at),
            CONSTRAINT fk_devices_user FOREIGN KEY (user_id) REFERENCES v4_users (id) ON DELETE CASCADE
        ) {$options}");
    }
};
