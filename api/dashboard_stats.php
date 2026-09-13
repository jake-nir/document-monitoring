<?php
/**
 * API: Dashboard statistics (JSON) for lightweight AJAX dashboard updates.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

header('Content-Type: application/json');

$pdo = db();
$uid = current_user_id();

$out = [];

switch ($_SESSION['role']) {
    case ROLE_BRANCH:
        $out['incoming'] = incoming_count($uid);
        $q = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE current_holder_id=?'); $q->execute([$uid]); $out['withMe'] = (int)$q->fetch()['c'];
        $q = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE created_by=?'); $q->execute([$uid]); $out['total'] = (int)$q->fetch()['c'];
        foreach (['PENDING','SIGNED','APPROVED','RTS'] as $s) {
            $q = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE created_by=? AND current_status=?'); $q->execute([$uid, $s]); $out[strtolower($s)] = (int)$q->fetch()['c'];
        }
        break;
    case ROLE_SECRETARY_DEPUTY:
    case ROLE_SECRETARY_CO:
        $out['incoming'] = incoming_count($uid);
        $q = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE current_holder_id=?'); $q->execute([$uid]); $out['withMe'] = (int)$q->fetch()['c'];
        foreach (['PENDING','SIGNED','APPROVED','RTS'] as $s) {
            $q = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE current_holder_id=? AND current_status=?'); $q->execute([$uid, $s]); $out[strtolower($s)] = (int)$q->fetch()['c'];
        }
        break;
    case ROLE_ADMIN:
        $out['total'] = (int)$pdo->query('SELECT COUNT(*) c FROM documents')->fetch()['c'];
        foreach (['PENDING','SIGNED','APPROVED','RTS'] as $s) {
            $q = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE current_status=?'); $q->execute([$s]); $out[strtolower($s)] = (int)$q->fetch()['c'];
        }
        $out['activeUsers'] = (int)$pdo->query('SELECT COUNT(*) c FROM users WHERE status="active"')->fetch()['c'];
        $out['activeBranches'] = (int)$pdo->query('SELECT COUNT(*) c FROM branches WHERE status="active"')->fetch()['c'];
        break;
}

$out['notifications'] = incoming_count($uid);
echo json_encode(['ok' => true, 'data' => $out]);
