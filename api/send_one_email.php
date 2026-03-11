<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

$automationMode = getSetting('automation_mode', 'cron');
if ($automationMode !== 'cron') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Cron mode is disabled. automation_mode = ' . $automationMode]);
    exit;
}
require_once __DIR__ . '/check_sending_limits.php';

header('Content-Type: application/json');

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$apiKey = $input['api_key'] ?? ($_GET['api_key'] ?? '');

if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Rate limit: 60 email sends per minute per API caller
RateLimiter::enforce($apiKey ?: RateLimiter::getIdentifier(), 'api/send_one_email', 60, 60);

$campaignId      = (int)($input['campaign_id']       ?? 0);
$followUpSeq     = max(1, (int)($input['follow_up_sequence'] ?? 1));
if (!$campaignId) {
    echo json_encode(['error' => 'campaign_id required']);
    exit;
}

$campaign = Database::fetchOne("SELECT * FROM campaigns WHERE id=?", [$campaignId]);
if (!$campaign) {
    echo json_encode(['error' => 'Campaign not found']);
    exit;
}

// ── Sending limit check ──────────────────────────────────────────────────
$limitCheck = checkSendingLimits($followUpSeq);
if (!$limitCheck['allowed']) {
    echo json_encode([
        'done'        => true,
        'limit_hit'   => true,
        'reason'      => $limitCheck['reason'],
        'sent'        => $campaign['sent_count'],
        'failed'      => $campaign['failed_count'],
    ]);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────

// If campaign is done, return summary
if ($campaign['status'] === 'completed') {
    echo json_encode(['done' => true, 'sent' => $campaign['sent_count'], 'failed' => $campaign['failed_count']]);
    exit;
}

// Get template
$tpl = Database::fetchOne("SELECT * FROM email_templates WHERE id=?", [$campaign['template_id']]);
if (!$tpl) {
    echo json_encode(['error' => 'Template not found']);
    exit;
}

// Build lead query
$where  = "l.status NOT IN ('unsubscribed','bounced','emailed')";
$params = [];
if ($campaign['filter_segment']) { $where .= ' AND l.segment=?';      $params[] = $campaign['filter_segment']; }
if ($campaign['filter_role'])    { $where .= ' AND l.role LIKE ?';     $params[] = '%' . $campaign['filter_role'] . '%'; }
if ($campaign['filter_province']){ $where .= ' AND l.province=?';     $params[] = $campaign['filter_province']; }

// For follow-up sequences: allow re-emailed leads but check no log exists for this sequence
if ($followUpSeq > 1) {
    $where  = "l.status NOT IN ('unsubscribed','bounced')";
    $params = [];
    if ($campaign['filter_segment']) { $where .= ' AND l.segment=?';  $params[] = $campaign['filter_segment']; }
    if ($campaign['filter_role'])    { $where .= ' AND l.role LIKE ?'; $params[] = '%' . $campaign['filter_role'] . '%'; }
    if ($campaign['filter_province']){ $where .= ' AND l.province=?'; $params[] = $campaign['filter_province']; }
}

$lead = Database::fetchOne(
    "SELECT l.* FROM leads l
     LEFT JOIN email_logs el ON el.lead_id = l.id AND el.campaign_id = ? AND el.follow_up_sequence = ?
     WHERE $where AND el.id IS NULL LIMIT 1",
    array_merge([$campaignId, $followUpSeq], $params)
);

if (!$lead) {
    // No more leads — mark complete
    Database::query(
        "UPDATE campaigns SET status='completed', completed_at=NOW() WHERE id=?",
        [$campaignId]
    );
    $camp = Database::fetchOne("SELECT sent_count, failed_count FROM campaigns WHERE id=?", [$campaignId]);
    echo json_encode(['done' => true, 'sent' => $camp['sent_count'], 'failed' => $camp['failed_count']]);
    exit;
}

// Mark campaign running
if ($campaign['status'] === 'draft') {
    Database::query("UPDATE campaigns SET status='running', started_at=NOW() WHERE id=?", [$campaignId]);
}

// Personalize template (no escaping — lead data is used inside HTML template)
$unsubLink = APP_URL . '/unsubscribe.php?email=' . urlencode($lead['email']);
$body = str_replace(
    ['{{first_name}}','{{last_name}}','{{full_name}}','{{role}}','{{company}}','{{city}}','{{province}}','{{email}}','{{unsubscribe_link}}'],
    [$lead['first_name'], $lead['last_name'], $lead['full_name'],
     $lead['role'], $lead['company'], $lead['city'],
     $lead['province'], $lead['email'], $unsubLink],
    $tpl['html_body']
);

// Prepend header image if set
if (!empty($tpl['header_image_url'])) {
    $headerHtml = '<div style="text-align:center;margin-bottom:20px"><img src="' . htmlspecialchars($tpl['header_image_url'], ENT_QUOTES) . '" style="max-width:100%;max-height:200px;border-radius:8px" alt="Header Image"></div>';
    $body = $headerHtml . $body;
}

// Append signature if set
if (!empty($tpl['signature_html'])) {
    $body .= '<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">' . $tpl['signature_html'];
}

$subject = str_replace(
    ['{{first_name}}','{{company}}'],
    [$lead['first_name'], $lead['company']],
    $tpl['subject']
);

// Send or log (test mode)
$status    = 'failed';
$msgId     = '';
$errorMsg  = '';
$sentVia   = 'smtp';

if ($campaign['test_mode']) {
    $status  = 'sent';
    $msgId   = 'test-' . uniqid();
    $sentVia = 'test';
} else {
    $tags   = ['campaign-' . $campaignId, 'seq-' . $followUpSeq];
    $attachmentPath = !empty($tpl['attachment_path']) ? __DIR__ . '/../' . $tpl['attachment_path'] : '';
    $result = EmailService::send($lead['email'], $lead['full_name'] ?: $lead['email'], $subject, $body, '', $tags, $attachmentPath);
    if ($result['success']) {
        $status  = 'sent';
        $msgId   = $result['message_id'] ?? '';
        $sentVia = $result['via'] ?? 'smtp';
    } else {
        $status   = 'failed';
        $errorMsg = $result['error'] ?? '';
    }
}

// Log
Database::query(
    "INSERT INTO email_logs (campaign_id,lead_id,recipient_email,recipient_name,subject,status,message_id,error_message,follow_up_sequence,sent_at)
     VALUES(?,?,?,?,?,?,?,?,?,NOW())",
    [$campaignId, $lead['id'], $lead['email'], $lead['full_name'], $subject, $status, $msgId, $errorMsg, $followUpSeq]
);

if ($status === 'sent') {
    Database::query("UPDATE leads SET status='emailed' WHERE id=? AND status='new'", [$lead['id']]);
    Database::query("UPDATE campaigns SET sent_count=sent_count+1 WHERE id=?",  [$campaignId]);
} else {
    Database::query("UPDATE campaigns SET failed_count=failed_count+1 WHERE id=?", [$campaignId]);
}

echo json_encode([
    'done'    => false,
    'sent_to' => $lead['email'],
    'status'  => $status,
    'via'     => $sentVia,
    'error'   => $errorMsg ?: null,
]);
