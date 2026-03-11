<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireSuperAdmin();

header('Content-Type: application/json');

try {
    Database::query("ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS attachments_json LONGTEXT NULL AFTER attachment_path");

    // Migrate existing single attachment_path values to attachments_json
    $rows = Database::fetchAll("SELECT id, attachment_path FROM email_templates WHERE attachment_path IS NOT NULL AND attachment_path != '' AND (attachments_json IS NULL OR attachments_json = '')");
    foreach ($rows as $row) {
        $json = json_encode([['path' => $row['attachment_path'], 'original' => basename($row['attachment_path'])]]);
        Database::query("UPDATE email_templates SET attachments_json=? WHERE id=?", [$json, $row['id']]);
    }
    echo json_encode(['success' => true, 'migrated' => count($rows)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
