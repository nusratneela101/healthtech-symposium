<?php
ob_start();

// Bootstrap: set session name and start session BEFORE loading config
require_once __DIR__ . '/../includes/session_bootstrap.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

ob_clean();
header('Content-Type: application/json');

// Accept session auth (admin UI) or API key (n8n)
if (!isset($_SESSION['user_id'])) {
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $apiKey = $input['api_key'] ?? ($_GET['api_key'] ?? '');
    if ($apiKey !== N8N_API_KEY || N8N_API_KEY === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
} else {
    Auth::requireSuperAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

$toEmail = filter_var(trim($input['to_email'] ?? ''), FILTER_VALIDATE_EMAIL);
if (!$toEmail) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid to_email address is required.']);
    exit;
}

$subject = trim($input['subject'] ?? 'Test Email — Canada Fintech Symposium');
$body    = trim($input['body']    ?? '');
if ($body === '') {
    $body = '<p>This is a test email sent from the <strong>Canada Fintech Symposium</strong> platform.</p>'
          . '<p>If you received this, your SMTP/email configuration is working correctly.</p>'
          . '<p style="color:#888;font-size:12px">Sent at: ' . date('Y-m-d H:i:s T') . '</p>';
}

$start  = microtime(true);
$result = EmailService::send($toEmail, $toEmail, $subject, $body);
$elapsed = round((microtime(true) - $start) * 1000); // ms

if ($result['success']) {
    echo json_encode([
        'success'    => true,
        'message'    => "Test email sent successfully to $toEmail",
        'via'        => $result['via'] ?? 'smtp',
        'message_id' => $result['message_id'] ?? '',
        'elapsed_ms' => $elapsed,
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success'    => false,
        'error'      => $result['error'] ?? 'Unknown error',
        'via'        => $result['via'] ?? 'smtp',
        'elapsed_ms' => $elapsed,
    ]);
}
