<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/imap.php';

header('Content-Type: application/json');

$apiKey = $_GET['api_key'] ?? (json_decode(file_get_contents('php://input'), true)['api_key'] ?? '');
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$result = ImapService::pollInbox();
echo json_encode($result);
