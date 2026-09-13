<?php
/**
 * API: Document search (JSON) for AJAX search across documents.
 * Role-aware: branch users see their own docs; secretaries see their own; admin sees all.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();

header('Content-Type: application/json');

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$where = ['1=1'];
$params = [];
$like = "%$q%";

// Scope by role
switch ($user['role']) {
    case ROLE_BRANCH:
        $where[] = '(d.created_by = ? OR d.originating_branch_id = ?)';
        array_push($params, (int)$user['id'], (int)$user['branch_id']);
        break;
    case ROLE_SECRETARY_DEPUTY:
    case ROLE_SECRETARY_CO:
        $where[] = 'd.current_holder_id = ?';
        $params[] = (int)$user['id'];
        break;
    // Admin: no scope
}

$where[] = '(d.tracking_number LIKE ? OR d.subject LIKE ? OR d.doc_control_number LIKE ? OR d.document_type LIKE ? OR d.sender LIKE ?)';
array_push($params, $like, $like, $like, $like, $like);

$whereSql = implode(' AND ', $where);
$stmt = db()->prepare("SELECT d.id, d.tracking_number, d.subject, d.current_status, d.priority, d.updated_at,
    ob.branch_name AS origin_branch, cu.full_name AS holder_name
    FROM documents d
    LEFT JOIN branches ob ON ob.id=d.originating_branch_id
    LEFT JOIN users cu ON cu.id=d.current_holder_id
    WHERE $whereSql ORDER BY d.updated_at DESC LIMIT 20");
$stmt->execute($params);
$rows = $stmt->fetchAll();

echo json_encode(['results' => array_map(function ($r) {
    return [
        'id' => (int)$r['id'],
        'tracking' => $r['tracking_number'],
        'subject' => $r['subject'],
        'status' => status_badge($r['current_status']),
        'status_label' => $r['current_status'],
        'priority' => $r['priority'],
        'origin' => $r['origin_branch'],
        'holder' => $r['holder_name'],
        'updated' => date('M d, Y g:iA', strtotime($r['updated_at'])),
    ];
}, $rows)]);
