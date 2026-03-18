<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
ob_clean();
header('Content-Type: application/json');

// Auth: accept N8N_API_KEY header/param OR active admin session
$apiKey = $_GET['api_key']
    ?? $_SERVER['HTTP_X_API_KEY']
    ?? (json_decode(file_get_contents('php://input'), true)['api_key'] ?? '');

$authed = (!empty($apiKey) && $apiKey === N8N_API_KEY)
       || (!empty($_SESSION['user_id']) && Auth::isAdmin());

if (!$authed) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'stats';

// ── Test action: just confirm the endpoint is reachable ──────────────────
if ($action === 'test') {
    // Optionally log the test ping so it appears in webhook_logs
    try {
        Database::query(
            "INSERT INTO webhook_logs (source, event_type, email, message_id, payload, received_at)
             VALUES ('brevo', 'test_ping', '', '', '{\"test\":true}', NOW())"
        );
    } catch (Exception $e) {
        // webhook_logs may not exist — that's fine
    }
    echo json_encode(['success' => true, 'message' => 'Webhook endpoint is reachable']);
    exit;
}

// ── Stats action ─────────────────────────────────────────────────────────
try {
    $data = [
        'webhook_logs_available' => false,
        'total_events'           => 0,
        'event_breakdown'        => [],
        'last_received'          => null,
        'unconfirmed_sent_count' => 0,
    ];

    // Count emails sent > 1 hour ago with no delivery confirmation
    $data['unconfirmed_sent_count'] = (int)(Database::fetchOne(
        "SELECT COUNT(*) AS c FROM email_logs
         WHERE status = 'sent' AND message_id IS NOT NULL AND message_id != ''
           AND sent_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    )['c'] ?? 0);

    // Webhook log stats (table may not exist)
    try {
        $data['last_received'] = Database::fetchOne(
            "SELECT received_at FROM webhook_logs WHERE source='brevo' ORDER BY received_at DESC LIMIT 1"
        )['received_at'] ?? null;

        $data['total_events'] = (int)(Database::fetchOne(
            "SELECT COUNT(*) AS c FROM webhook_logs WHERE source='brevo'"
        )['c'] ?? 0);

        $breakdown = Database::fetchAll(
            "SELECT event_type, COUNT(*) AS cnt FROM webhook_logs WHERE source='brevo'
             GROUP BY event_type ORDER BY cnt DESC"
        );
        foreach ($breakdown as $row) {
            $data['event_breakdown'][$row['event_type']] = (int)$row['cnt'];
        }

        $data['webhook_logs_available'] = true;
    } catch (Exception $e) {
        // webhook_logs table does not exist
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
