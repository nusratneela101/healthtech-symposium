<?php
class MsGraph {

    private static function tenantUrl(): string {
        $tenant = MS_OAUTH_TENANT_ID ?: 'common';
        return "https://login.microsoftonline.com/{$tenant}";
    }

    public static function getAuthUrl(): string {
        $params = http_build_query([
            'client_id'     => MS_OAUTH_CLIENT_ID,
            'response_type' => 'code',
            'redirect_uri'  => MS_OAUTH_REDIRECT_URI,
            'scope'         => 'Mail.Read Mail.Send offline_access',
            'response_mode' => 'query',
        ]);
        return self::tenantUrl() . '/oauth2/v2.0/authorize?' . $params;
    }

    public static function exchangeCode(string $code): ?array {
        return self::tokenRequest([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => MS_OAUTH_REDIRECT_URI,
            'client_id'     => MS_OAUTH_CLIENT_ID,
            'client_secret' => MS_OAUTH_CLIENT_SECRET,
            'scope'         => 'Mail.Read Mail.Send offline_access',
        ]);
    }

    public static function refreshToken(string $refreshToken): ?array {
        return self::tokenRequest([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => MS_OAUTH_CLIENT_ID,
            'client_secret' => MS_OAUTH_CLIENT_SECRET,
            'scope'         => 'Mail.Read Mail.Send offline_access',
        ]);
    }

    private static function tokenRequest(array $params): ?array {
        $ch = curl_init(self::tenantUrl() . '/oauth2/v2.0/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($body, true);
        if (empty($data['access_token'])) return null;
        return $data;
    }

    public static function getStoredToken(): ?string {
        $row = Database::fetchOne(
            "SELECT * FROM oauth_accounts WHERE provider='microsoft' ORDER BY id DESC LIMIT 1"
        );
        if (!$row) return null;

        // Refresh if expired (with 60s buffer)
        if (!empty($row['token_expires_at']) &&
            strtotime($row['token_expires_at']) < (time() + 60)) {
            $new = self::refreshToken($row['refresh_token']);
            if ($new) {
                $expiresAt = date('Y-m-d H:i:s', time() + (int)($new['expires_in'] ?? 3600));
                Database::query(
                    "UPDATE oauth_accounts SET access_token=?, refresh_token=?, token_expires_at=?, updated_at=NOW()
                     WHERE id=?",
                    [$new['access_token'], $new['refresh_token'] ?? $row['refresh_token'], $expiresAt, $row['id']]
                );
                return $new['access_token'];
            }
            return null;
        }
        return $row['access_token'];
    }

    public static function pollInbox(int $sinceMinutes = 30): array {
        $token = self::getStoredToken();
        if (!$token) return ['saved' => 0, 'skipped' => 0, 'error' => 'No OAuth token'];

        $since = date('c', strtotime("-{$sinceMinutes} minutes"));
        $filter = urlencode("receivedDateTime ge $since");
        $url = "https://graph.microsoft.com/v1.0/me/messages?\$filter={$filter}&\$top=50&\$select=id,subject,from,body,receivedDateTime,internetMessageId";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token", 'Content-Type: application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($body, true);

        $saved   = 0;
        $skipped = 0;

        foreach ($data['value'] ?? [] as $msg) {
            $fromEmail  = $msg['from']['emailAddress']['address'] ?? '';
            $fromName   = $msg['from']['emailAddress']['name']    ?? '';
            $subject    = $msg['subject']    ?? '';
            $bodyText   = strip_tags($msg['body']['content'] ?? '');
            $bodyHtml   = $msg['body']['contentType'] === 'html' ? ($msg['body']['content'] ?? '') : '';
            $msgId      = $msg['internetMessageId'] ?? $msg['id'];
            $receivedAt = date('Y-m-d H:i:s', strtotime($msg['receivedDateTime'] ?? 'now'));

            // Check for existing
            $exists = Database::fetchOne("SELECT id FROM responses WHERE message_id=?", [$msgId]);
            if ($exists) { $skipped++; continue; }

            // Match lead
            $lead = Database::fetchOne("SELECT id, campaign_id FROM leads WHERE email=?", [$fromEmail]);
            $leadId     = $lead['id'] ?? null;
            $campaignId = null;
            if ($leadId) {
                $lastLog = Database::fetchOne(
                    "SELECT campaign_id FROM email_logs WHERE lead_id=? ORDER BY sent_at DESC LIMIT 1",
                    [$leadId]
                );
                $campaignId = $lastLog['campaign_id'] ?? null;
            }

            Database::query(
                "INSERT INTO responses (lead_id, campaign_id, from_email, from_name, subject, body_text, body_html, message_id, received_at)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$leadId, $campaignId, $fromEmail, $fromName, $subject, $bodyText, $bodyHtml, $msgId, $receivedAt]
            );
            if ($leadId) {
                Database::query("UPDATE leads SET status='responded' WHERE id=? AND status='emailed'", [$leadId]);
            }
            $saved++;
        }

        return ['saved' => $saved, 'skipped' => $skipped];
    }

    public static function sendMail(string $to, string $subject, string $htmlBody, string $inReplyTo = ''): array {
        $token = self::getStoredToken();
        if (!$token) return ['success' => false, 'error' => 'No OAuth token'];

        $payload = [
            'message' => [
                'subject' => $subject,
                'body'    => ['contentType' => 'HTML', 'content' => $htmlBody],
                'toRecipients' => [['emailAddress' => ['address' => $to]]],
            ],
            'saveToSentItems' => true,
        ];
        if ($inReplyTo) {
            $payload['message']['internetMessageHeaders'] = [
                ['name' => 'In-Reply-To', 'value' => $inReplyTo],
                ['name' => 'References',  'value' => $inReplyTo],
            ];
        }

        $ch = curl_init('https://graph.microsoft.com/v1.0/me/sendMail');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 202) return ['success' => true];
        $err = json_decode($body, true);
        return ['success' => false, 'error' => $err['error']['message'] ?? 'Send failed'];
    }
}
