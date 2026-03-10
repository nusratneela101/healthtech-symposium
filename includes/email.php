<?php
class EmailService {

    /**
     * Read a setting from the site_settings DB table.
     * Falls back to $fallback if the key is missing, empty, or the DB is unavailable.
     */
    private static function getDbSetting(string $key, string $fallback): string {
        try {
            if (!class_exists('Database')) {
                $dbFile = __DIR__ . '/../config/database.php';
                if (!file_exists($dbFile)) {
                    return $fallback;
                }
                require_once $dbFile;
            }
            $row = Database::fetchOne(
                "SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1",
                [$key]
            );
            $val = $row['setting_value'] ?? '';
            return ($val !== '') ? $val : $fallback;
        } catch (Exception $e) {
            return $fallback;
        }
    }

    private static function getMailer(): object {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        $src      = __DIR__ . '/../phpmailer/src/PHPMailer.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        } elseif (file_exists($src)) {
            require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
            require_once __DIR__ . '/../phpmailer/src/SMTP.php';
            require_once __DIR__ . '/../phpmailer/src/Exception.php';
        } else {
            throw new RuntimeException('PHPMailer not found. Install via composer or place in phpmailer/src/');
        }
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = self::getDbSetting('smtp_host',   SMTP_HOST);
        $mail->SMTPAuth   = true;
        $mail->Username   = self::getDbSetting('smtp_user',   SMTP_USER);
        $mail->Password   = self::getDbSetting('smtp_pass',   SMTP_PASS);
        $mail->SMTPSecure = self::getDbSetting('smtp_secure', SMTP_SECURE);
        $rawPort = self::getDbSetting('smtp_port', (string) SMTP_PORT);
        $mail->Port       = is_numeric($rawPort) ? (int) $rawPort : SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->setFrom(
            self::getDbSetting('smtp_from_email', SMTP_FROM_EMAIL),
            self::getDbSetting('smtp_from_name',  SMTP_FROM_NAME)
        );
        return $mail;
    }

    /**
     * Add anti-spam headers and List-Unsubscribe to a PHPMailer instance.
     * Extracts the unsubscribe URL from the HTML body if present.
     */
    private static function addAntiSpamHeaders(object $mail, string $htmlBody): void {
        // Precedence: bulk signals to ISPs this is a bulk/campaign message
        $mail->addCustomHeader('Precedence', 'bulk');

        // X-Mailer: identify the sending application
        $appName = defined('APP_NAME') ? APP_NAME : 'EmailApp';
        $mail->addCustomHeader('X-Mailer', $appName . ' Mailer');

        // List-Unsubscribe header (one-click unsubscribe per RFC 8058 / Gmail requirements)
        $unsubUrl = '';
        if (preg_match('/href=["\']([^"\']*unsubscribe[^"\']*)["\']/', $htmlBody, $m)) {
            $unsubUrl = $m[1];
        }
        if ($unsubUrl) {
            $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubUrl . '>');
            $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        // X-Auto-Response-Suppress: avoid out-of-office loops for bulk sends
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
    }

    /**
     * Convert HTML to a readable plain-text alternative.
     * Preserves links in the form "text <url>" and cleans whitespace.
     */
    public static function htmlToText(string $html): string {
        // Replace links with "text <url>"
        $text = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', '$2 <$1>', $html);
        if ($text === null) {
            $text = $html;
        }
        // Replace block-level tags with newlines
        $text = preg_replace('/<(br|p|div|h[1-6]|li|tr)[^>]*>/i', "\n", $text);
        if ($text === null) {
            $text = strip_tags($html);
        }
        // Strip remaining tags
        $text = strip_tags($text);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse excessive blank lines
        $collapsed = preg_replace('/(\n\s*){3,}/', "\n\n", $text);
        return trim($collapsed ?? $text);
    }

    /**
     * Read the active email provider from site_settings.
     * Returns an empty string if the setting is not found or the DB is unavailable.
     */
    private static function getActiveProvider(): string {
        if (function_exists('getSetting')) {
            return getSetting('email_provider', '');
        }
        return self::getDbSetting('email_provider', '');
    }

    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        array  $tags = [],
        string $attachmentPath = ''
    ): array {
        $provider = self::getActiveProvider();

        // Use Brevo API only when explicitly selected as the email provider
        if ($provider === 'brevo' && defined('BREVO_API_KEY') && BREVO_API_KEY !== '') {
            require_once __DIR__ . '/brevo.php';
            $result = Brevo::sendTransactional($toEmail, $toName, $subject, $htmlBody, $tags, $attachmentPath);
            if ($result['success']) {
                return ['success' => true, 'message_id' => $result['message_id'], 'via' => 'brevo'];
            }
            return ['success' => false, 'error' => $result['error'], 'via' => 'brevo'];
        }

        // Use PHPMailer SMTP for all other providers (ms365, cpanel, smtp, etc.)
        try {
            $mail = self::getMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject  = $subject;
            $mail->isHTML(true);
            $mail->Body     = $htmlBody;
            $mail->AltBody  = $textBody ?: self::htmlToText($htmlBody);
            self::addAntiSpamHeaders($mail, $htmlBody);
            if ($attachmentPath !== '' && file_exists($attachmentPath)) {
                $mail->addAttachment($attachmentPath);
            }
            $mail->send();
            return ['success' => true, 'message_id' => $mail->getLastMessageID(), 'via' => 'smtp'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'via' => 'smtp'];
        }
    }

    public static function sendReply(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $inReplyTo = ''
    ): array {
        try {
            $mail = self::getMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject  = $subject;
            $mail->isHTML(true);
            $mail->Body     = $htmlBody;
            $mail->AltBody  = self::htmlToText($htmlBody);
            if ($inReplyTo) {
                $mail->addCustomHeader('In-Reply-To', $inReplyTo);
                $mail->addCustomHeader('References',  $inReplyTo);
            }
            $mail->send();
            return ['success' => true, 'message_id' => $mail->getLastMessageID()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
