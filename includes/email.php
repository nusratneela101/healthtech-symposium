<?php
class EmailService {
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
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        return $mail;
    }

    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): array {
        try {
            $mail = self::getMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject  = $subject;
            $mail->isHTML(true);
            $mail->Body     = $htmlBody;
            $mail->AltBody  = $textBody ?: strip_tags($htmlBody);
            $mail->send();
            return ['success' => true, 'message_id' => $mail->getLastMessageID()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
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
            $mail->AltBody  = strip_tags($htmlBody);
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
