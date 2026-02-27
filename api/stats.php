<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    $leads     = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM leads")['c'] ?? 0);
    $sent      = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM email_logs WHERE status='sent'")['c'] ?? 0);
    $responses = (int)(Database::fetchOne("SELECT COUNT(*) AS c FROM responses")['c'] ?? 0);
    echo json_encode(['leads' => $leads, 'sent' => $sent, 'responses' => $responses]);
} catch (Exception $e) {
    echo json_encode(['leads' => 0, 'sent' => 0, 'responses' => 0]);
}
