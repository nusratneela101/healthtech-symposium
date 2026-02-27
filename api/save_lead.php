<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$apiKey  = $input['api_key'] ?? ($_GET['api_key'] ?? '');

if ($apiKey !== N8N_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$leads   = $input['leads'] ?? [];
$created = 0;
$skipped = 0;

foreach ($leads as $l) {
    $email = strtolower(trim($l['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }
    $fn = trim($l['first_name'] ?? '');
    $ln = trim($l['last_name']  ?? '');
    $segment = trim($l['segment'] ?? 'Other');
    $validSegs = ['Financial Institutions','Technology & Solution Providers','Venture Capital / Investors','FinTech Startups','Other'];
    if (!in_array($segment, $validSegs)) $segment = 'Other';
    try {
        Database::query(
            "INSERT IGNORE INTO leads
             (first_name,last_name,full_name,email,company,job_title,role,segment,country,province,city,source)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $fn, $ln, trim($l['full_name'] ?? "$fn $ln"), $email,
                trim($l['company']   ?? ''), trim($l['job_title'] ?? ''),
                trim($l['role']      ?? ''), $segment,
                trim($l['country']   ?? 'Canada'), trim($l['province'] ?? ''),
                trim($l['city']      ?? ''), trim($l['source']   ?? 'API'),
            ]
        );
        $created++;
    } catch (Exception $e) {
        $skipped++;
    }
}

echo json_encode(['success' => true, 'created' => $created, 'skipped' => $skipped]);
