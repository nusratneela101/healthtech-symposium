<?php
ob_start();
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
ob_clean();
header('Content-Type: application/json');

// Always return 200 so Brevo does not retry
$raw    = file_get_contents('php://input');
$events = json_decode($raw, true);

if (empty($events)) {
    echo json_encode(['success' => true, 'message' => 'No events']);
    exit;
}

// Handle both single event object and array of events
if (isset($events['event'])) {
    $events = [$events];
}

foreach ($events as $evt) {
    $eventType = $evt['event']       ?? '';
    $email     = strtolower(trim($evt['email']      ?? ''));
    $messageId = trim($evt['message-id'] ?? ($evt['messageId'] ?? ''));

    if (!$email) continue;

    try {
        switch ($eventType) {

            case 'delivered':
                if ($messageId) {
                    Database::query(
                        "UPDATE email_logs SET status='delivered' WHERE message_id=?",
                        [$messageId]
                    );
                } else {
                    Database::query(
                        "UPDATE email_logs SET status='delivered'
                         WHERE recipient_email=? AND status='sent'
                         ORDER BY sent_at DESC LIMIT 1",
                        [$email]
                    );
                }
                break;

            case 'opened':
                Database::query(
                    "UPDATE email_logs SET opened=1, opened_at=NOW()
                     WHERE recipient_email=? AND opened=0
                     ORDER BY sent_at DESC LIMIT 1",
                    [$email]
                );
                break;

            case 'clicked':
                Database::query(
                    "UPDATE email_logs SET clicked=1
                     WHERE recipient_email=?
                     ORDER BY sent_at DESC LIMIT 1",
                    [$email]
                );
                break;

            case 'hardBounce':
            case 'softBounce':
            case 'blocked':
            case 'invalid_email':
                if ($messageId) {
                    Database::query(
                        "UPDATE email_logs SET status='bounced' WHERE message_id=?",
                        [$messageId]
                    );
                }
                Database::query(
                    "UPDATE email_logs SET status='bounced'
                     WHERE recipient_email=? AND status IN ('sent','delivered')
                     ORDER BY sent_at DESC LIMIT 1",
                    [$email]
                );
                Database::query(
                    "UPDATE leads SET status='bounced' WHERE email=?",
                    [$email]
                );
                break;

            case 'unsubscribed':
                Database::query(
                    "UPDATE leads SET status='unsubscribed' WHERE email=?",
                    [$email]
                );
                Database::query(
                    "UPDATE email_logs SET status='unsubscribed'
                     WHERE recipient_email=? ORDER BY sent_at DESC LIMIT 1",
                    [$email]
                );
                break;

            case 'spam':
                Database::query(
                    "UPDATE leads SET status='unsubscribed' WHERE email=?",
                    [$email]
                );
                break;

            case 'error':
            case 'deferred':
                if ($messageId) {
                    Database::query(
                        "UPDATE email_logs SET status='failed' WHERE message_id=?",
                        [$messageId]
                    );
                }
                break;
        }

        // Log every webhook event for debugging
        try {
            Database::query(
                "INSERT INTO webhook_logs (source, event_type, email, message_id, payload, received_at)
                 VALUES ('brevo', ?, ?, ?, ?, NOW())",
                [$eventType, $email, $messageId, json_encode($evt)]
            );
        } catch (Exception $e) {
            // webhook_logs table may not exist — ignore silently
        }

    } catch (Exception $e) {
        error_log('Brevo webhook error [' . $eventType . '] ' . $email . ': ' . $e->getMessage());
    }
}

echo json_encode(['success' => true]);
