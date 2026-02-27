<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$apiKey = $_GET['api_key'] ?? (json_decode(file_get_contents('php://input'), true)['api_key'] ?? '');
if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$where  = "status NOT IN ('unsubscribed','bounced')";
$params = [];

if (!empty($_GET['segment'])) {
    $where .= ' AND segment = ?';
    $params[] = $_GET['segment'];
}
if (!empty($_GET['role'])) {
    $where .= ' AND role LIKE ?';
    $params[] = '%' . $_GET['role'] . '%';
}
if (!empty($_GET['province'])) {
    $where .= ' AND province = ?';
    $params[] = $_GET['province'];
}

$limit  = min((int)($_GET['limit'] ?? 5000), 5000);
$leads  = Database::fetchAll(
    "SELECT id,first_name,last_name,full_name,email,company,job_title,role,segment,province,city
     FROM leads WHERE $where ORDER BY id ASC LIMIT " . $limit,
    $params
);

echo json_encode(['total' => count($leads), 'leads' => $leads]);
