<?php
/**
 * API: Return unread notification count + latest notifications as JSON.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();

header('Content-Type: application/json');

$stmt = db()->prepare('SELECT n.*, d.tracking_number FROM notifications n LEFT JOIN documents d ON d.id=n.document_id WHERE n.user_id=? ORDER BY n.created_at DESC LIMIT 10');
$stmt->execute([(int)$user['id']]);
$list = $stmt->fetchAll();

echo json_encode([
    'unread' => incoming_count((int)$user['id']),
    'items' => array_map(function ($n) {
        return [
            'id' => (int)$n['id'],
            'message' => $n['message'],
            'tracking' => $n['tracking_number'],
            'document_id' => (int)$n['document_id'],
            'is_read' => (bool)$n['is_read'],
            'created' => date('M d, Y g:i A', strtotime($n['created_at'])),
        ];
    }, $list),
]);
