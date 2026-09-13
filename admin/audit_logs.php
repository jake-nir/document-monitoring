<?php
/**
 * Admin: Audit logs (login history, user actions, document actions).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_ADMIN);
$pdo = db();

$tab = $_GET['tab'] ?? 'audit'; // audit | login
$module = $_GET['module'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$limit = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

if ($tab === 'login') {
    $where = ['1=1']; $params = [];
    if ($module !== '') { $where[] = 'username LIKE ?'; $params[] = "%$module%"; }
    $whereSql = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM login_logs WHERE $whereSql"); $stmt->execute($params);
    $totalRows = (int)$stmt->fetch()['c'];
    $totalPages = max(1, (int)ceil($totalRows/$limit));
    $stmt = $pdo->prepare("SELECT * FROM login_logs WHERE $whereSql ORDER BY logged_at DESC LIMIT $limit OFFSET $offset"); $stmt->execute($params);
    $rows = $stmt->fetchAll();
} else {
    $where = ['1=1']; $params = [];
    if ($module !== '') { $where[] = 'module = ?'; $params[] = $module; }
    if ($actionFilter !== '') { $where[] = 'action LIKE ?'; $params[] = "%$actionFilter%"; }
    $whereSql = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM audit_logs WHERE $whereSql"); $stmt->execute($params);
    $totalRows = (int)$stmt->fetch()['c'];
    $totalPages = max(1, (int)ceil($totalRows/$limit));
    $stmt = $pdo->prepare("SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE $whereSql ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset"); $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

$modules = $pdo->query('SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL ORDER BY module')->fetchAll(PDO::FETCH_COLUMN);

page_header('Audit Logs', 'audit', $user);
?>
<h4 class="mb-3"><i class="bi bi-journal-text me-2"></i>Audit &amp; Login Logs</h4>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $tab==='audit'?'active':'' ?>" href="?tab=audit">Audit Trail</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='login'?'active':'' ?>" href="?tab=login">Login History</a></li>
</ul>

<form method="get" class="row g-2 mb-3">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <?php if ($tab === 'audit'): ?>
    <div class="col-auto"><select name="module" class="form-select form-select-sm"><option value="">All modules</option><?php foreach ($modules as $m): ?><option value="<?= e($m) ?>" <?= $module===$m?'selected':'' ?>><?= e($m) ?></option><?php endforeach; ?></select></div>
    <div class="col-auto"><input name="action" value="<?= e($actionFilter) ?>" class="form-control form-control-sm" placeholder="Search action"></div>
    <div class="col-auto"><button class="btn btn-primary btn-sm">Filter</button></div>
    <?php else: ?>
    <div class="col-auto"><input name="module" value="<?= e($module) ?>" class="form-control form-control-sm" placeholder="Search username"></div>
    <div class="col-auto"><button class="btn btn-primary btn-sm">Filter</button></div>
    <?php endif; ?>
</form>

<div class="card shadow-sm border-0"><div class="card-body p-0">
<table class="table table-hover table-sm align-middle mb-0">
<thead class="table-light small">
    <?php if ($tab === 'login'): ?>
    <tr><th>When</th><th>User</th><th>IP</th><th>User Agent</th><th>Result</th></tr>
    <?php else: ?>
    <tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>IP</th><th>Details</th></tr>
    <?php endif; ?>
</thead>
<tbody>
<?php if ($tab === 'login'): ?>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td class="small text-muted"><?= e(date('M d, Y g:iA', strtotime($r['logged_at']))) ?></td>
        <td class="small"><?= e($r['username'] ?? '—') ?></td>
        <td class="small"><?= e($r['ip_address'] ?? '—') ?></td>
        <td class="small text-truncate" style="max-width:200px"><?= e($r['user_agent'] ?? '—') ?></td>
        <td><?= $r['success'] ? '<span class="badge bg-success">Success</span>' : '<span class="badge bg-danger">Failed</span>' ?></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <?php foreach ($rows as $r): ?>
    <tr>
        <td class="small text-muted"><?= e(date('M d, Y g:iA', strtotime($r['created_at']))) ?></td>
        <td class="small"><?= e($r['full_name'] ?? 'System') ?></td>
        <td class="small text-uppercase"><?= e($r['action']) ?></td>
        <td class="small"><?= e($r['module'] ?? '—') ?></td>
        <td class="small"><?= $r['record_id'] ? (int)$r['record_id'] : '—' ?></td>
        <td class="small"><?= e($r['ip_address'] ?? '—') ?></td>
        <td class="small text-truncate" style="max-width:220px"><?= e($r['details'] ?? '—') ?></td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
<?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted small py-4">No records.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-end">
<?php for ($i=1;$i<=$totalPages;$i++): ?><li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?tab=<?= e($tab) ?>&module=<?= e($module) ?>&action=<?= e($actionFilter) ?>&page=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php page_footer(); ?>
