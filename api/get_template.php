<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$id      = (int)($_GET['id'] ?? 0);
$preview = isset($_GET['preview']);

if ($id) {
    $tpl = Database::fetchOne("SELECT * FROM email_templates WHERE id=?", [$id]);
} else {
    $tpl = Database::fetchOne("SELECT * FROM email_templates WHERE is_default=1 LIMIT 1");
    if (!$tpl) {
        $tpl = Database::fetchOne("SELECT * FROM email_templates ORDER BY id ASC LIMIT 1");
    }
}

if (!$tpl) {
    http_response_code(404);
    echo json_encode(['error' => 'Template not found']);
    exit;
}

if ($preview) {
    header('Content-Type: text/html');
    echo $tpl['html_body'];
    exit;
}

echo json_encode([
    'id'        => $tpl['id'],
    'name'      => $tpl['name'],
    'subject'   => $tpl['subject'],
    'html_body' => $tpl['html_body'],
]);
