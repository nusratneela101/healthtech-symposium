<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$apiKey = $input['api_key'] ?? ($_GET['api_key'] ?? '');
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
try {
    $result   = Database::query("UPDATE leads SET status='new' WHERE status='emailed'");
    $affected = Database::rowCount($result);
    echo json_encode(['success' => true, 'reset_count' => $affected]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
