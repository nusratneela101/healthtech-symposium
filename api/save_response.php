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

$fromEmail = strtolower(trim($input['from_email'] ?? ''));
$fromName  = trim($input['from_name']  ?? '');
$subject   = trim($input['subject']    ?? '');
$bodyText  = trim($input['body_text']  ?? '');
$bodyHtml  = trim($input['body_html']  ?? '');
$msgId     = trim($input['message_id'] ?? '');
$respType  = trim($input['response_type'] ?? 'other');
$campaignId= (int)($input['campaign_id'] ?? 0) ?: null;

if (!$fromEmail) {
    echo json_encode(['error' => 'from_email required']);
    exit;
}

$lead = Database::fetchOne("SELECT id FROM leads WHERE email=?", [$fromEmail]);
$leadId = $lead ? $lead['id'] : null;

try {
    Database::query(
        "INSERT IGNORE INTO responses
         (lead_id,campaign_id,from_email,from_name,subject,body_text,body_html,message_id,response_type,received_at)
         VALUES(?,?,?,?,?,?,?,?,?,NOW())",
        [$leadId, $campaignId, $fromEmail, $fromName, $subject, $bodyText, $bodyHtml, $msgId ?: null, $respType]
    );
    if ($leadId) {
        Database::query("UPDATE leads SET status='responded' WHERE id=?", [$leadId]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
