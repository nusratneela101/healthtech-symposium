<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$logId = (int)($_GET['id'] ?? 0);
$hmac  = $_GET['h'] ?? '';

if ($logId && N8N_API_KEY !== '') {
    $expected = hash_hmac('sha256', (string)$logId, N8N_API_KEY);
    if (hash_equals($expected, $hmac)) {
        try {
            Database::query(
                "UPDATE email_logs SET opened_at = COALESCE(opened_at, NOW()) WHERE id = ?",
                [$logId]
            );
        } catch (Exception $e) {
            // Silently ignore — must still return the pixel
        }
    }
} elseif ($logId && N8N_API_KEY === '') {
    // No secret configured — still track (degraded mode)
    try {
        Database::query(
            "UPDATE email_logs SET opened_at = COALESCE(opened_at, NOW()) WHERE id = ?",
            [$logId]
        );
    } catch (Exception $e) {}
}

// Return 1×1 transparent GIF
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
