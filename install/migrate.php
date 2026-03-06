<?php
/**
 * Migration script for existing installations.
 * Creates any tables that may be missing from an older install.
 *
 * Protection: requires the MIGRATE_SECRET env var, the N8N_API_KEY, or the
 * built-in token "migrate2026" to be supplied as:
 *   - ?token=<secret>  or  ?secret=<secret>  query parameters
 *   - Authorization: Bearer <secret> header
 *
 * Example: /install/migrate.php?token=migrate2026
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
$expectedSecret = $_ENV['MIGRATE_SECRET'] ?? (defined('N8N_API_KEY') ? N8N_API_KEY : '');
// Only fall back to the built-in token when no custom secret is configured
if ($expectedSecret === '') {
    $expectedSecret = 'migrate2026';
}
$providedSecret = '';

// Accept Bearer token header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($authHeader, 'Bearer ')) {
    $providedSecret = substr($authHeader, 7);
}
// Accept ?token= or ?secret= query param
if ($providedSecret === '' && isset($_GET['token'])) {
    $providedSecret = $_GET['token'];
}
if ($providedSecret === '' && isset($_GET['secret'])) {
    $providedSecret = $_GET['secret'];
}

$authorized = hash_equals($expectedSecret, $providedSecret);

if (!$authorized) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body>';
    echo '<h2 style="color:red">403 Forbidden</h2>';
    echo '<p>Supply the correct secret via <code>?token=</code> or <code>Authorization: Bearer</code>.</p>';
    echo '<p>Example: <code>migrate.php?token=migrate2026</code></p>';
    echo '</body></html>';
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
      `title` varchar(200) NOT NULL DEFAULT '',
      `message` text NOT NULL DEFAULT '',
      `type` enum('info','success','warning','error') DEFAULT 'info',
      `is_read` tinyint(1) DEFAULT 0,
      `link` varchar(500) DEFAULT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `is_read` (`is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
    'name' => 'notifications.title_column',
    'sql'  => "ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `title` varchar(200) NOT NULL DEFAULT ''",
];

$migrations[] = [
    'name' => 'notifications.type_column',
    'sql'  => "ALTER TABLE `notifications` ADD COLUMN IF NOT EXISTS `type` enum('info','success','warning','error') DEFAULT 'info'",
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

$migrations[] = [
    'name' => 'lead_collection_items',
    'sql'  => "CREATE TABLE IF NOT EXISTS `lead_collection_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `collection_id` int(11) NOT NULL,
      `lead_id` int(11) NOT NULL,
      `action` enum('created','skipped','duplicate') DEFAULT 'created',
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_collection` (`collection_id`),
      KEY `idx_lead` (`lead_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
    'name' => 'lead_tags',
    'sql'  => "CREATE TABLE IF NOT EXISTS `lead_tags` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `color` varchar(7) DEFAULT '#0d6efd',
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
    'name' => 'lead_tag_map',
    'sql'  => "CREATE TABLE IF NOT EXISTS `lead_tag_map` (
      `lead_id` int(11) NOT NULL,
      `tag_id` int(11) NOT NULL,
      PRIMARY KEY (`lead_id`, `tag_id`),
      FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`tag_id`) REFERENCES `lead_tags`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
    'name' => 'rate_limits',
    'sql'  => "CREATE TABLE IF NOT EXISTS `rate_limits` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `identifier` varchar(200) NOT NULL,
      `endpoint` varchar(200) NOT NULL,
      `requests` int DEFAULT 1,
      `window_start` datetime NOT NULL,
      PRIMARY KEY (`id`),
      KEY `identifier_endpoint` (`identifier`, `endpoint`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$migrations[] = [
    'name' => 'campaigns.scheduled_at',
    'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `scheduled_at` datetime DEFAULT NULL",
];

$migrations[] = [
    'name' => 'campaigns.scheduled_by',
    'sql'  => "ALTER TABLE `campaigns` ADD COLUMN IF NOT EXISTS `scheduled_by` int(11) DEFAULT NULL",
];

$migrations[] = [
    'name' => 'campaigns.status_scheduled',
    'sql'  => "ALTER TABLE `campaigns` MODIFY COLUMN `status` enum('draft','scheduled','running','completed','paused') DEFAULT 'draft'",
];

$migrations[] = [
    'name' => 'responses.hot_alert_sent',
    'sql'  => "ALTER TABLE `responses` ADD COLUMN IF NOT EXISTS `hot_alert_sent` tinyint(1) NOT NULL DEFAULT 0",
];

$migrations[] = [
    'name' => 'leads.score',
    'sql'  => "ALTER TABLE `leads` ADD COLUMN IF NOT EXISTS `score` int DEFAULT 0",
];

// ── Run ───────────────────────────────────────────────────────────────────────
$ok = 0;
$fail = 0;
$results = [];

foreach ($migrations as $m) {
    try {
        Database::query($m['sql']);
        $results[] = ['status' => 'ok', 'name' => $m['name'], 'msg' => ''];
        $ok++;
    } catch (Exception $e) {
        $results[] = ['status' => 'fail', 'name' => $m['name'], 'msg' => $e->getMessage()];
        $fail++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Database Migration</title>
<style>
  body { font-family: monospace; background: #0a0f1a; color: #e2e8f0; padding: 32px; }
  h1   { color: #0d6efd; margin-bottom: 24px; }
  .step { padding: 8px 12px; margin: 4px 0; border-radius: 6px; font-size: 14px; }
  .ok   { background: #0d2e1a; color: #4ade80; }
  .fail { background: #2e0d0d; color: #f87171; }
  .summary { margin-top: 24px; font-size: 16px; font-weight: bold; }
  .ok-sum  { color: #4ade80; }
  .fail-sum{ color: #f87171; }
</style>
</head>
<body>
<h1>🗄️ Database Migration</h1>
<?php foreach ($results as $r): ?>
<div class="step <?php echo $r['status']; ?>">
  <?php echo $r['status'] === 'ok' ? '✅' : '❌'; ?>
  <strong><?php echo htmlspecialchars($r['name']); ?></strong>
  <?php if ($r['msg']): ?>
    — <span style="opacity:.8"><?php echo htmlspecialchars($r['msg']); ?></span>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<div class="summary">
  Done.
  <span class="ok-sum"><?php echo $ok; ?> succeeded</span>,
  <span class="fail-sum"><?php echo $fail; ?> failed</span>.
</div>
</body>
</html>
