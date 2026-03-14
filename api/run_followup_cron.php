<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/check_sending_limits.php';

ob_clean();
header('Content-Type: application/json');

$automationMode = getSetting('automation_mode', 'cron');
if ($automationMode !== 'cron') {
    echo json_encode(['success' => false, 'error' => 'Cron mode is disabled. automation_mode = ' . $automationMode]);
    exit;
}

$apiKey = $_GET['api_key'] ?? (json_decode(file_get_contents('php://input'), true)['api_key'] ?? '');
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Log cron heartbeat — actual follow-up sending is handled by send_one_email.php
// called per-lead by cPanel or the front-end scheduler.
$startMs    = microtime(true);
$durationMs = (int)round((microtime(true) - $startMs) * 1000);
$errors = 0;

try {
    Database::query(
        "INSERT INTO cron_log (job_name, status, message, duration_ms)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), message=VALUES(message),
                                 duration_ms=VALUES(duration_ms), last_run=NOW()",
        ['followup_sender', 'ok', 'Follow-up cron heartbeat', $durationMs]
    );
} catch (Exception $e) {
    $errors++;
}

echo json_encode(['success' => $errors === 0, 'message' => 'Follow-up cron is active in cron mode']);
