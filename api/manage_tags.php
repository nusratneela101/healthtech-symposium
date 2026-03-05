<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

Auth::check();

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($input['action'] ?? $_GET['action'] ?? '');

switch ($method) {
    // ── List tags (optionally with lead assignments) ─────────────────────────
    case 'GET':
        if ($action === 'lead_tags') {
            $leadId = (int)($_GET['lead_id'] ?? 0);
            if (!$leadId) { http_response_code(400); echo json_encode(['error' => 'lead_id required']); exit; }
            $tags = Database::fetchAll(
                "SELECT t.id, t.name, t.color FROM lead_tags t
                 INNER JOIN lead_tag_map m ON m.tag_id = t.id
                 WHERE m.lead_id = ? ORDER BY t.name",
                [$leadId]
            );
            echo json_encode(['success' => true, 'tags' => $tags]);
        } else {
            $tags = Database::fetchAll("SELECT * FROM lead_tags ORDER BY name");
            echo json_encode(['success' => true, 'tags' => $tags]);
        }
        break;

    // ── Create / Update / Assign / Remove ───────────────────────────────────
    case 'POST':
        Auth::requireSuperAdmin();

        if ($action === 'create') {
            $name  = trim($input['name'] ?? '');
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', $input['color'] ?? '') ? $input['color'] : '#0d6efd';
            if (!$name) { http_response_code(400); echo json_encode(['error' => 'name required']); exit; }
            try {
                Database::query("INSERT INTO lead_tags (name, color) VALUES (?, ?)", [$name, $color]);
                $id = (int)Database::lastInsertId();
                echo json_encode(['success' => true, 'id' => $id]);
            } catch (Exception $e) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Tag name already exists']);
            }

        } elseif ($action === 'update') {
            $id    = (int)($input['id'] ?? 0);
            $name  = trim($input['name'] ?? '');
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', $input['color'] ?? '') ? $input['color'] : '#0d6efd';
            if (!$id || !$name) { http_response_code(400); echo json_encode(['error' => 'id and name required']); exit; }
            Database::query("UPDATE lead_tags SET name = ?, color = ? WHERE id = ?", [$name, $color, $id]);
            echo json_encode(['success' => true]);

        } elseif ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'id required']); exit; }
            Database::query("DELETE FROM lead_tags WHERE id = ?", [$id]);
            echo json_encode(['success' => true]);

        } elseif ($action === 'assign') {
            $leadId = (int)($input['lead_id'] ?? 0);
            $tagId  = (int)($input['tag_id']  ?? 0);
            if (!$leadId || !$tagId) { http_response_code(400); echo json_encode(['error' => 'lead_id and tag_id required']); exit; }
            try {
                Database::query(
                    "INSERT IGNORE INTO lead_tag_map (lead_id, tag_id) VALUES (?, ?)",
                    [$leadId, $tagId]
                );
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }

        } elseif ($action === 'unassign') {
            $leadId = (int)($input['lead_id'] ?? 0);
            $tagId  = (int)($input['tag_id']  ?? 0);
            if (!$leadId || !$tagId) { http_response_code(400); echo json_encode(['error' => 'lead_id and tag_id required']); exit; }
            Database::query("DELETE FROM lead_tag_map WHERE lead_id = ? AND tag_id = ?", [$leadId, $tagId]);
            echo json_encode(['success' => true]);

        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action. Valid: create, update, delete, assign, unassign']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
