<?php
class ImapService {
    public static function pollInbox(): array {
        if (!function_exists('imap_open')) {
            return ['error' => 'IMAP extension not available'];
        }
        $mbox = @imap_open(IMAP_HOST, IMAP_USER, IMAP_PASS);
        if (!$mbox) {
            return ['error' => 'Cannot connect to IMAP: ' . imap_last_error()];
        }
        $uids = imap_search($mbox, 'UNSEEN');
        if (!$uids) {
            imap_close($mbox);
            return ['saved' => 0, 'skipped' => 0];
        }
        $uids = array_slice($uids, 0, 50);
        $saved = 0;
        $skipped = 0;
        foreach ($uids as $uid) {
            $header  = imap_headerinfo($mbox, $uid);
            $msgId   = trim($header->message_id ?? '');
            $fromEmail = strtolower(trim($header->from[0]->mailbox . '@' . $header->from[0]->host));
            $fromName  = isset($header->from[0]->personal)
                ? imap_utf8($header->from[0]->personal)
                : $fromEmail;
            $subject   = imap_utf8($header->subject ?? '');
            $bodyText  = '';
            $bodyHtml  = '';
            $structure = imap_fetchstructure($mbox, $uid);
            if ($structure->type === 0) {
                $raw = imap_fetchbody($mbox, $uid, '1');
                $enc = $structure->encoding ?? 0;
                $bodyText = self::decode($raw, $enc);
            } else {
                $parts = $structure->parts ?? [];
                foreach ($parts as $i => $part) {
                    $raw = imap_fetchbody($mbox, $uid, $i + 1);
                    $dec = self::decode($raw, $part->encoding ?? 0);
                    if ($part->subtype === 'HTML') {
                        $bodyHtml = $dec;
                    } else {
                        $bodyText .= $dec;
                    }
                }
            }
            $responseType = self::classify($subject, $bodyText);
            $lead = Database::fetchOne("SELECT id FROM leads WHERE email = ?", [$fromEmail]);
            $leadId = $lead ? $lead['id'] : null;
            $campaignId = null;
            if ($leadId) {
                $logRow = Database::fetchOne(
                    "SELECT campaign_id FROM email_logs WHERE lead_id = ? ORDER BY sent_at DESC LIMIT 1",
                    [$leadId]
                );
                $campaignId = $logRow['campaign_id'] ?? null;
            }
            try {
                Database::query(
                    "INSERT IGNORE INTO responses
                     (lead_id, campaign_id, from_email, from_name, subject, body_text, body_html, message_id, response_type, received_at)
                     VALUES (?,?,?,?,?,?,?,?,?, NOW())",
                    [$leadId, $campaignId, $fromEmail, $fromName, $subject, $bodyText, $bodyHtml, $msgId ?: null, $responseType]
                );
                if (Database::getConnection()->lastInsertId() > 0) {
                    $saved++;
                    if ($leadId) {
                        Database::query("UPDATE leads SET status='responded' WHERE id=?", [$leadId]);
                    }
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                $skipped++;
            }
            imap_setflag_full($mbox, (string)$uid, '\\Seen', ST_UID);
        }
        imap_close($mbox);
        return ['saved' => $saved, 'skipped' => $skipped];
    }

    private static function decode(string $data, int $encoding): string {
        switch ($encoding) {
            case 1: return imap_utf8($data);
            case 2: return imap_binary($data);
            case 3: return base64_decode($data);
            case 4: return quoted_printable_decode($data);
            default: return $data;
        }
    }

    private static function classify(string $subject, string $body): string {
        $text = strtolower($subject . ' ' . $body);
        if (preg_match('/auto.?reply|out of office|vacation|away/i', $text)) return 'auto_reply';
        if (preg_match('/bounce|undeliverable|failed|permanent failure/i', $text))   return 'bounce';
        if (preg_match('/not interested|unsubscribe|remove me|opt.?out/i', $text))   return 'not_interested';
        if (preg_match('/more info|more information|tell me more|send details/i', $text)) return 'more_info';
        if (preg_match('/interested|register|sign up|yes|attend|join/i', $text))     return 'interested';
        return 'other';
    }
}
