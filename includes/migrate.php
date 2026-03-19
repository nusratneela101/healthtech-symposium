<?php
/**
 * Auto-migration runner.
 *
 * Checks the stored schema_version in site_settings and runs any pending
 * migration files from the migrations/ directory.
 *
 * Called once per request from includes/session_bootstrap.php.
 * Uses a PHP lock-file to avoid running more than once per process.
 */

// Guard: only run once per PHP process (in case session_bootstrap is included multiple times)
if (defined('MIGRATE_DONE')) {
    return;
}
define('MIGRATE_DONE', true);

// Silently skip if the Database class is not yet loaded
if (!class_exists('Database')) {
    return;
}

// Current target schema version (increment when adding new migration files)
const SCHEMA_VERSION_TARGET = 1;

$migrationsDir = __DIR__ . '/../migrations';

try {
    // Ensure site_settings table exists before querying it
    Database::query("CREATE TABLE IF NOT EXISTS `site_settings` (
      `id`            INT AUTO_INCREMENT PRIMARY KEY,
      `setting_key`   VARCHAR(100) NOT NULL,
      `setting_value` TEXT,
      `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed schema_version = 0 if not present
    Database::query("INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('schema_version', '0')");

    $row = Database::fetchOne("SELECT setting_value FROM `site_settings` WHERE setting_key = 'schema_version'");
    $currentVersion = (int)($row['setting_value'] ?? 0);

    if ($currentVersion >= SCHEMA_VERSION_TARGET) {
        return; // Nothing to do
    }

    // Run migrations from currentVersion+1 up to SCHEMA_VERSION_TARGET
    for ($v = $currentVersion + 1; $v <= SCHEMA_VERSION_TARGET; $v++) {
        $file = $migrationsDir . '/' . sprintf('%03d', $v) . '_fix_schema.php';
        if (!file_exists($file)) {
            continue;
        }

        $steps = require $file;
        if (!is_array($steps)) {
            continue;
        }

        foreach ($steps as $step) {
            if (empty($step['sql'])) {
                continue;
            }
            try {
                Database::query($step['sql']);
            } catch (Exception $e) {
                $msg = $e->getMessage();
                // Ignore "already exists" / duplicate-key errors — they are expected on re-runs
                if (stripos($msg, 'Duplicate key name') !== false
                    || stripos($msg, 'already exists') !== false
                    || stripos($msg, 'Duplicate entry') !== false
                ) {
                    continue;
                }
                // Log other errors but do not abort the whole migration
                error_log("migrate.php step '{$step['name']}' failed: " . $msg);
            }
        }

        // Mark this version as applied
        Database::query(
            "UPDATE `site_settings` SET `setting_value` = ? WHERE `setting_key` = 'schema_version'",
            [$v]
        );
    }
} catch (Exception $e) {
    // Migration failures must never break normal page load
    error_log('migrate.php error: ' . $e->getMessage());
}
