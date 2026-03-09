<?php
class Brevo {

    private static function request(string $method, string $endpoint, array $payload = []): array {
        $url = 'https://api.brevo.com/v3' . $endpoint;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'api-key: ' . BREVO_API_KEY,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'GET' && !empty($payload)) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($payload));
        }
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($body, true) ?? []];
    }

    public static function sendTransactional(
        string $to,
        string $toName,
        string $subject,
        string $htmlBody,
        array  $tags = [],
        string $attachmentPath = ''
    ): array {
        $payload = [
            'sender'      => ['email' => SMTP_FROM_EMAIL, 'name' => SMTP_FROM_NAME],
            'to'          => [['email' => $to, 'name' => $toName]],
            'subject'     => $subject,
            'htmlContent' => $htmlBody,
        ];
        if ($tags) {
            $payload['tags'] = $tags;
        }
        if ($attachmentPath !== '' && file_exists($attachmentPath)) {
            $payload['attachment'] = [
                [
                    'name'    => basename($attachmentPath),
                    'content' => base64_encode(file_get_contents($attachmentPath)),
                ],
            ];
        }

        $res = self::request('POST', '/smtp/email', $payload);
        if ($res['code'] === 201 && !empty($res['body']['messageId'])) {
            return ['success' => true, 'message_id' => $res['body']['messageId']];
        }
        $errMsg = $res['body']['message'] ?? ('HTTP ' . $res['code']);
        return ['success' => false, 'error' => $errMsg];
    }

    public static function getEmailEvents(string $messageId): array {
        $res = self::request('GET', '/smtp/statistics/events', ['messageId' => $messageId]);
        return $res['body']['events'] ?? [];
    }

    public static function getStats(string $tag, string $startDate, string $endDate): array {
        $res = self::request('GET', '/smtp/statistics/aggregatedReport', [
            'tag'       => $tag,
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ]);
        return $res['body'] ?? [];
    }
}
