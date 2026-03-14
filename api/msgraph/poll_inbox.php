<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/msgraph.php';

header('Content-Type: application/json');

$apiKey = $_GET['api_key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$since = max(1, (int)($_GET['since_minutes'] ?? 30));

// Check if OAuth is configured
$hasOAuth = Database::fetchOne("SELECT id FROM oauth_accounts WHERE provider='microsoft' LIMIT 1");

if ($hasOAuth) {
    require_once __DIR__ . '/../../includes/functions.php';
    $result = MsGraph::pollInbox($since);
    echo json_encode(array_merge(['source' => 'msgraph'], $result));
} else {
    // Fall back to existing IMAP poll endpoint if no OAuth token
    $pollUrl = APP_URL . '/api/poll_inbox.php?api_key=' . urlencode(N8N_API_KEY);
    $ch = curl_init($pollUrl);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]);
    $body = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($body, true) ?? ['error' => 'IMAP fallback failed'];
    echo json_encode(array_merge(['source' => 'imap_fallback'], $data));
}
