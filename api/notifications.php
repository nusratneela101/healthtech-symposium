<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

Auth::check();
$userId = Auth::user()['id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return unread notifications for current user (or global notifications with null user_id)
    try {
        $notifications = Database::fetchAll(
            "SELECT * FROM notifications
             WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0
             ORDER BY created_at DESC LIMIT 20",
            [$userId]
        );
        $unreadCount = count($notifications);
        echo json_encode(['success' => true, 'notifications' => $notifications, 'unread' => $unreadCount]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'notifications' => [], 'unread' => 0]);
    }
    exit;
}

if ($method === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = trim($input['action'] ?? 'mark_read');

    if ($action === 'mark_read') {
        $ids = $input['ids'] ?? [];
        if ($ids === 'all') {
            Database::query(
                "UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL",
                [$userId]
            );
            echo json_encode(['success' => true]);
        } elseif (is_array($ids) && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            // Only mark as read if the notification belongs to this user (or is global)
            Database::query(
                "UPDATE notifications SET is_read = 1
                 WHERE id IN ($placeholders) AND (user_id = ? OR user_id IS NULL)",
                array_merge(array_map('intval', $ids), [$userId])
            );
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ids required']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
