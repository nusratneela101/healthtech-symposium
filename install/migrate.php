<?php
/**
 * Migration script for existing installations.
 * Creates any tables that may be missing from an older install.
 *
 * Protection: requires the N8N_API_KEY (or MIGRATE_SECRET env var) to be
 * supplied as a Bearer token or as a ?secret= query-string parameter.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────────────────────
$expectedSecret = $_ENV['MIGRATE_SECRET'] ?? (defined('N8N_API_KEY') ? N8N_API_KEY : '');
$providedSecret = '';

// Accept Bearer token header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($authHeader, 'Bearer ')) {
    $providedSecret = substr($authHeader, 7);
}
// Or accept ?secret= query param
if ($providedSecret === '' && isset($_GET['secret'])) {
    $providedSecret = $_GET['secret'];
}

if ($expectedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
    http_response_code(403);
    echo "403 Forbidden — supply the correct secret via ?secret= or Authorization: Bearer <secret>\n";
    exit;
}

// ── Migrations ────────────────────────────────────────────────────────────────
$migrations = [];

$migrations[] = [
    'name' => 'site_settings',
    'sql'  => "CREATE TABLE IF NOT EXISTS `site_settings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `setting_key` varchar(100) NOT NULL,
      `setting_value` text,
      `setting_group` varchar(50) DEFAULT 'general',
      `updated_by` int(11) DEFAULT NULL,
      `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
    'name' => 'notifications',
    'sql'  => "CREATE TABLE IF NOT EXISTS `notifications` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) DEFAULT NULL,
      `message` text NOT NULL,
      `link` varchar(500) DEFAULT '',
      `is_read` tinyint(1) DEFAULT 0,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
    'name' => 'lead_collections',
    'sql'  => "CREATE TABLE IF NOT EXISTS `lead_collections` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `source` varchar(100) DEFAULT 'Apollo',
      `total_fetched` int(11) DEFAULT 0,
      `total_saved` int(11) DEFAULT 0,
      `total_skipped` int(11) DEFAULT 0,
      `status` enum('running','done','failed') DEFAULT 'running',
      `search_params` text,
      `started_at` datetime DEFAULT NULL,
      `completed_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// ── Run ───────────────────────────────────────────────────────────────────────
$ok = 0;
$fail = 0;
echo "Running migrations...\n\n";

foreach ($migrations as $m) {
    try {
        Database::query($m['sql']);
        echo "[OK]   {$m['name']}\n";
        $ok++;
    } catch (Exception $e) {
        echo "[FAIL] {$m['name']}: " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\nDone. $ok succeeded, $fail failed.\n";
