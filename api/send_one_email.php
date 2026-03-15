<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
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
$where  = "l.status NOT IN ('unsubscribed','bounced','emailed') AND l.email NOT LIKE '%@noemail.placeholder'";
$params = [];
if ($campaign['filter_segment']) { $where .= ' AND LOWER(TRIM(l.segment))=LOWER(TRIM(?))';  $params[] = $campaign['filter_segment']; }
if ($campaign['filter_role'])    { $where .= ' AND l.role LIKE ?';                           $params[] = '%' . $campaign['filter_role'] . '%'; }
if ($campaign['filter_province']){ $where .= ' AND LOWER(TRIM(l.province))=LOWER(TRIM(?))'; $params[] = $campaign['filter_province']; }

// For follow-up sequences: allow re-emailed leads but check no log exists for this sequence
if ($followUpSeq > 1) {
    $where  = "l.status NOT IN ('unsubscribed','bounced') AND l.email NOT LIKE '%@noemail.placeholder'";
    $params = [];
    if ($campaign['filter_segment']) { $where .= ' AND LOWER(TRIM(l.segment))=LOWER(TRIM(?))';  $params[] = $campaign['filter_segment']; }
    if ($campaign['filter_role'])    { $where .= ' AND l.role LIKE ?';                           $params[] = '%' . $campaign['filter_role'] . '%'; }
    if ($campaign['filter_province']){ $where .= ' AND LOWER(TRIM(l.province))=LOWER(TRIM(?))'; $params[] = $campaign['filter_province']; }
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
$unsubLink     = PUBLIC_URL . '/unsubscribe.php?email=' . urlencode($lead['email']);
$signatureHtml = $tpl['signature_html'] ?? '';
$body = str_replace(
    ['{{first_name}}','{{last_name}}','{{full_name}}','{{role}}','{{company}}','{{city}}','{{province}}','{{email}}','{{unsubscribe_link}}','{{signature}}'],
    [$lead['first_name'], $lead['last_name'], $lead['full_name'],
     $lead['role'], $lead['company'], $lead['city'],
     $lead['province'], $lead['email'], $unsubLink, $signatureHtml],
    $tpl['html_body']
);
// Second pass: replace {{unsubscribe_link}} that may appear inside the injected signature HTML
$body = str_replace('{{unsubscribe_link}}', $unsubLink, $body);

// Prepend header image if set
if (!empty($tpl['header_image_url'])) {
    $headerHtml = '<div style="text-align:center;margin-bottom:20px"><img src="' . htmlspecialchars($tpl['header_image_url'], ENT_QUOTES) . '" style="max-width:100%;max-height:200px;border-radius:8px" alt="Header Image"></div>';
    $body = $headerHtml . $body;
}

// Append signature if set AND template did not use {{signature}} placeholder
if (!empty($tpl['signature_html']) && strpos($tpl['html_body'], '{{signature}}') === false) {
    $appendedSig = str_replace('{{unsubscribe_link}}', $unsubLink, $tpl['signature_html']);
    $body .= '<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">' . $appendedSig;
}

$subject = str_replace(
    ['{{first_name}}','{{company}}'],
    [$lead['first_name'], $lead['company']],
    $tpl['subject']
);

// Strip Promotions-triggering words from the start of the subject line
$subject = preg_replace('/^(Invitation|Invite|Registration|Event|Conference|Symposium|Newsletter):\s*/i', '', $subject);
$subject = trim($subject);

// Wrap in professional email shell
$body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>' . htmlspecialchars($subject, ENT_QUOTES) . '</title>
<style type="text/css">
body { margin:0; padding:0; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #222222; background-color: #ffffff; }
a { color: #1a6bbf; }
p { margin: 0 0 12px 0; }
</style>
</head>
<body>
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#ffffff;">
<tr><td align="center">
<table cellpadding="0" cellspacing="0" border="0" width="680" style="max-width:680px;">
<tr><td style="padding: 24px 32px;">
' . $body . '
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';

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
    $attachmentPaths = [];
    if (!empty($tpl['attachments_json'])) {
        $attList = json_decode($tpl['attachments_json'], true) ?: [];
        foreach ($attList as $att) {
            $fullPath = __DIR__ . '/../' . $att['path'];
            if (file_exists($fullPath)) {
                $attachmentPaths[] = $fullPath;
            }
        }
    } elseif (!empty($tpl['attachment_path'])) {
        // Legacy fallback
        $p = __DIR__ . '/../' . $tpl['attachment_path'];
        if (file_exists($p)) {
            $attachmentPaths[] = $p;
        }
    }
    $result = EmailService::send($lead['email'], $lead['full_name'] ?: $lead['email'], $subject, $body, '', $tags, $attachmentPaths);
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
