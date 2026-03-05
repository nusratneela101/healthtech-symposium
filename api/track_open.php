<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$logId = (int)($_GET['id'] ?? 0);
if ($logId) {
    try {
        Database::query(
            "UPDATE email_logs SET opened_at = COALESCE(opened_at, NOW()) WHERE id = ?",
            [$logId]
        );
    } catch (Exception $e) {
        // Silently ignore — must still return the pixel
    }
}

// Return 1×1 transparent GIF
header('Content-Type: image/gif');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
