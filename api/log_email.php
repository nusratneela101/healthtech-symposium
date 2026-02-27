<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$apiKey = $input['api_key'] ?? ($_GET['api_key'] ?? '');

if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$campaignId  = (int)($input['campaign_id']     ?? 0) ?: null;
$leadId      = (int)($input['lead_id']         ?? 0) ?: null;
$recipEmail  = trim($input['recipient_email']  ?? '');
$recipName   = trim($input['recipient_name']   ?? '');
$subject     = trim($input['subject']          ?? '');
$status      = trim($input['status']           ?? 'sent');
$msgId       = trim($input['message_id']       ?? '');
$errorMsg    = trim($input['error_message']    ?? '');

if (!$recipEmail) {
    echo json_encode(['error' => 'recipient_email required']);
    exit;
}

try {
    Database::query(
        "INSERT INTO email_logs
         (campaign_id,lead_id,recipient_email,recipient_name,subject,status,message_id,error_message,sent_at)
         VALUES(?,?,?,?,?,?,?,?,NOW())",
        [$campaignId, $leadId, $recipEmail, $recipName, $subject, $status, $msgId, $errorMsg]
    );
    if ($leadId && $status === 'sent') {
        Database::query("UPDATE leads SET status='emailed' WHERE id=? AND status='new'", [$leadId]);
    }
    echo json_encode(['success' => true, 'log_id' => Database::lastInsertId()]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
