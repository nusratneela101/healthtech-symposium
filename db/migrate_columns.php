<?php
/**
 * Migration script to add missing columns to email_logs.
 * Run once after deployment to fix existing databases.
 * Requires a valid session (logged-in user) or a valid api_key.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Security: require a valid session or a valid N8N api_key
$authenticated = false;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['user_id'])) {
    $authenticated = true;
}

if (!$authenticated) {
    $apiKey = $_REQUEST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!empty($apiKey) && defined('N8N_API_KEY') && hash_equals(N8N_API_KEY, $apiKey)) {
        $authenticated = true;
    }
}

if (!$authenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Allowed identifiers whitelist — prevents SQL injection if this pattern is reused
$allowedTables  = ['email_logs'];
$allowedColumns = ['follow_up_sequence', 'opened_at', 'message_id', 'error_message', 'sent_at'];

$columns = [
    ['table' => 'email_logs', 'column' => 'follow_up_sequence', 'sql' => 'INT DEFAULT 1'],
    ['table' => 'email_logs', 'column' => 'opened_at',          'sql' => 'DATETIME NULL'],
    ['table' => 'email_logs', 'column' => 'message_id',         'sql' => 'VARCHAR(255) NULL'],
    ['table' => 'email_logs', 'column' => 'error_message',      'sql' => 'TEXT NULL'],
    ['table' => 'email_logs', 'column' => 'sent_at',            'sql' => 'DATETIME NULL'],
];

$migrations = [];

foreach ($columns as $col) {
    if (!in_array($col['table'], $allowedTables, true) || !in_array($col['column'], $allowedColumns, true)) {
        $migrations[] = "Skipped (not whitelisted): {$col['table']}.{$col['column']}";
        continue;
    }

    $exists = Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [$col['table'], $col['column']]
    );
    if (($exists['cnt'] ?? 0) == 0) {
        Database::query("ALTER TABLE `{$col['table']}` ADD COLUMN `{$col['column']}` {$col['sql']}");
        $migrations[] = "Added {$col['table']}.{$col['column']}";
    } else {
        $migrations[] = "Already exists: {$col['table']}.{$col['column']}";
    }
}

echo json_encode(['success' => true, 'migrations' => $migrations]);
