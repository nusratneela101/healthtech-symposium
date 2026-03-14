<?php
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$id      = (int)($_GET['id'] ?? 0);
$preview = isset($_GET['preview']);

try {
    if ($id) {
        $tpl = Database::fetchOne("SELECT * FROM email_templates WHERE id = ?", [$id]);
        if ($tpl && !(bool)($tpl['is_active'] ?? true)) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Template not found or inactive.',
                'hint'  => 'The requested template exists but is currently inactive.',
                'id'    => $id,
            ]);
            exit;
        }
        if (!$tpl) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Template not found.',
                'hint'  => 'No template with id=' . $id . ' exists.',
                'id'    => $id,
            ]);
            exit;
        }
    } else {
        $tpl = Database::fetchOne("SELECT * FROM email_templates WHERE is_default = 1 AND is_active = 1 LIMIT 1");
        if (!$tpl) {
            $tpl = Database::fetchOne("SELECT * FROM email_templates WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
        }    }

    if (!$tpl) {
        http_response_code(404);
        echo json_encode([
            'error' => 'No active templates found.',
            'hint'  => 'Create at least one active email template in the admin panel.',
        ]);
        exit;
    }

    if ($preview) {
        header('Content-Type: text/html');
        // Replace common placeholders with sample data for preview
        $html = $tpl['html_body'];
        $placeholders = [
            '{{first_name}}'      => 'Jane',
            '{{last_name}}'       => 'Doe',
            '{{full_name}}'       => 'Jane Doe',
            '{{email}}'           => 'jane.doe@example.com',
            '{{company}}'         => 'Example Corp',
            '{{job_title}}'       => 'Chief Medical Officer',
            '{{unsubscribe_url}}' => '#',
            '{{unsubscribe_link}}' => '#',
            '{{signature}}'       => '<em style="color:#888">[Signature block]</em>',
        ];
        echo str_replace(array_keys($placeholders), array_values($placeholders), $html);
        exit;
    }

    $response = [
        'id'         => (int)$tpl['id'],
        'name'       => $tpl['name'],
        'subject'    => $tpl['subject'],
        'html_body'  => $tpl['html_body'],
        'is_default' => (bool)($tpl['is_default'] ?? false),
        'created_at' => $tpl['created_at'] ?? null,
    ];

    if (defined('APP_DEBUG') && APP_DEBUG) {
        $response['debug'] = ['query_id' => $id, 'preview' => $preview];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log('get_template.php PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error while fetching template.',
        'hint'  => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : null,
    ]);
} catch (Exception $e) {
    error_log('get_template.php Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred.',
        'hint'  => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : null,
    ]);
}
