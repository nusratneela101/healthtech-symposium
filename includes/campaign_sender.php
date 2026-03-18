<?php
/**
 * Core email-sending helper for cron campaign runners.
 *
 * Requires (already included by the calling file):
 *   includes/email.php, config/database.php, config/config.php
 */

/**
 * Send one campaign email to a lead, log the result, and update campaign counters.
 *
 * @param  array  $campaign    Row from campaigns table
 * @param  array  $tpl         Row from email_templates table
 * @param  array  $lead        Row from leads table
 * @param  int    $followUpSeq 1 = normal campaign email, >1 = follow-up sequence
 * @return array  ['status' => 'sent'|'failed', 'via' => string, 'error' => string]
 */
function sendCampaignEmail(array $campaign, array $tpl, array $lead, int $followUpSeq = 1): array
{
    $campaignId    = (int)$campaign['id'];

    // Re-check campaign status before sending (user may have paused/stopped since the loop started)
    $freshCampaign = Database::fetchOne(
        "SELECT status FROM campaigns WHERE id=?",
        [$campaignId]
    );
    if (!$freshCampaign || $freshCampaign['status'] !== 'running') {
        return ['status' => 'skipped', 'reason' => 'Campaign no longer running'];
    }

    $unsubLink     = PUBLIC_URL . '/unsubscribe.php?email=' . urlencode($lead['email']);
    $signatureHtml = $tpl['signature_html'] ?? '';

    // Personalise template body
    $body = str_replace(
        ['{{first_name}}', '{{last_name}}', '{{full_name}}', '{{role}}', '{{company}}',
         '{{city}}', '{{province}}', '{{email}}', '{{unsubscribe_link}}', '{{signature}}'],
        [$lead['first_name'], $lead['last_name'], $lead['full_name'],
         $lead['role'], $lead['company'], $lead['city'],
         $lead['province'], $lead['email'], $unsubLink, $signatureHtml],
        $tpl['html_body']
    );
    // Second pass: replace {{unsubscribe_link}} that may appear inside the injected signature
    $body = str_replace('{{unsubscribe_link}}', $unsubLink, $body);

    // Prepend header image if set
    if (!empty($tpl['header_image_url'])) {
        $headerHtml = '<div style="text-align:center;margin-bottom:20px">'
            . '<img src="' . htmlspecialchars($tpl['header_image_url'], ENT_QUOTES)
            . '" style="max-width:100%;max-height:200px;border-radius:8px" alt="Header Image">'
            . '</div>';
        $body = $headerHtml . $body;
    }

    // Append signature if template did not use {{signature}} placeholder
    if (!empty($tpl['signature_html']) && strpos($tpl['html_body'], '{{signature}}') === false) {
        $appendedSig = str_replace('{{unsubscribe_link}}', $unsubLink, $tpl['signature_html']);
        $body .= '<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0">' . $appendedSig;
    }

    // Personalise subject
    $subject = str_replace(
        ['{{first_name}}', '{{company}}'],
        [$lead['first_name'], $lead['company']],
        $tpl['subject']
    );
    // Strip Promotions-triggering words from subject start
    $subject = preg_replace('/^(Invitation|Invite|Registration|Event|Conference|Symposium|Newsletter):\s*/i', '', $subject);
    $subject = trim($subject);

    // Wrap body in professional XHTML email shell
    $body = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"'
        . ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
        . '<html xmlns="http://www.w3.org/1999/xhtml">'
        . '<head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0"/>'
        . '<title>' . htmlspecialchars($subject, ENT_QUOTES) . '</title>'
        . '<style type="text/css">'
        . 'body { margin:0; padding:0; font-family: Arial, sans-serif; font-size: 14px;'
        . ' line-height: 1.6; color: #222222; background-color: #ffffff; }'
        . 'a { color: #1a6bbf; } p { margin: 0 0 12px 0; }'
        . '</style>'
        . '</head>'
        . '<body>'
        . '<table cellpadding="0" cellspacing="0" border="0" width="100%"'
        . ' style="background-color:#ffffff;">'
        . '<tr><td align="center">'
        . '<table cellpadding="0" cellspacing="0" border="0" width="680"'
        . ' style="max-width:680px;">'
        . '<tr><td style="padding: 24px 32px;">'
        . $body
        . '</td></tr></table></td></tr></table>'
        . '</body></html>';

    $status   = 'failed';
    $msgId    = '';
    $errorMsg = '';
    $sentVia  = 'smtp';

    if (!empty($campaign['test_mode'])) {
        $status  = 'sent';
        $msgId   = 'test-' . uniqid();
        $sentVia = 'test';
    } else {
        $tags            = ['campaign-' . $campaignId, 'seq-' . $followUpSeq];
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
            $p = __DIR__ . '/../' . $tpl['attachment_path'];
            if (file_exists($p)) {
                $attachmentPaths[] = $p;
            }
        }

        $result = EmailService::send(
            $lead['email'],
            $lead['full_name'] ?: $lead['email'],
            $subject,
            $body,
            '',
            $tags,
            $attachmentPaths
        );

        if ($result['success']) {
            $status  = 'sent';
            $msgId   = $result['message_id'] ?? '';
            $sentVia = $result['via'] ?? 'smtp';
        } else {
            $errorMsg = $result['error'] ?? '';
        }
    }

    // Guard: ensure status is never saved as blank
    if (empty($status)) {
        $status = 'failed';
    }

    // Log email attempt
    Database::query(
        "INSERT INTO email_logs
             (campaign_id, lead_id, recipient_email, recipient_name, subject,
              status, message_id, error_message, follow_up_sequence, sent_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [$campaignId, $lead['id'], $lead['email'], $lead['full_name'],
         $subject, $status, $msgId, $errorMsg, $followUpSeq]
    );

    // Update lead and campaign counters
    if ($status === 'sent') {
        Database::query("UPDATE leads SET status='emailed' WHERE id=? AND status='new'", [$lead['id']]);
        Database::query("UPDATE campaigns SET sent_count=sent_count+1 WHERE id=?", [$campaignId]);
    } else {
        Database::query("UPDATE campaigns SET failed_count=failed_count+1 WHERE id=?", [$campaignId]);
    }

    return ['status' => $status, 'via' => $sentVia, 'error' => $errorMsg];
}
