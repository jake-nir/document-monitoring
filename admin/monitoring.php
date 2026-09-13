<?php
/**
 * Admin: Live Document Monitoring. View all active documents with filters,
 * current holder, age, last action, and visual overdue indicators.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_ADMIN);
$pdo = db();

$q    = trim((string)($_GET['q'] ?? ''));
$status = $_GET['status'] ?? '';
$branch = (int)($_GET['branch'] ?? 0);
$holder = (int)($_GET['holder'] ?? 0);
$limit = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = ['1=1'];
$params = [];
if ($q !== '') { $where[] = '(d.tracking_number LIKE ? OR d.subject LIKE ? OR d.doc_control_number LIKE ?)'; $like="%$q%"; array_push($params,$like,$like,$like); }
if ($status !== '') { $where[] = 'd.current_status = ?'; $params[] = $status; }
if ($branch > 0) { $where[] = 'd.originating_branch_id = ?'; $params[] = $branch; }
if ($holder > 0) { $where[] = 'd.current_holder_id = ?'; $params[] = $holder; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM documents d WHERE $whereSql");
$stmt->execute($params); $totalRows = (int)$stmt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalRows / $limit));

$stmt = $pdo->prepare("SELECT d.*, ob.branch_name AS origin_branch, cu.full_name AS holder_name, cu.role AS holder_role,
    (SELECT action FROM document_tracking WHERE document_id=d.id ORDER BY id DESC LIMIT 1) AS last_action
    FROM documents d
    LEFT JOIN branches ob ON ob.id=d.originating_branch_id
    LEFT JOIN users cu ON cu.id=d.current_holder_id
    WHERE $whereSql ORDER BY d.updated_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();

$branches = $pdo->query('SELECT * FROM branches ORDER BY branch_name')->fetchAll();
$holders = $pdo->query("SELECT u.id, u.full_name, u.role FROM users u WHERE u.status='active' AND u.role IN ('BRANCH','SECRETARY_DEPUTY','SECRETARY_CO') ORDER BY u.full_name")->fetchAll();

page_header('Live Document Monitoring', 'monitoring', $user);
?>
<h4 class="mb-3"><i class="bi bi-activity me-2"></i>Live Document Monitoring</h4>

<form method="get" class="card shadow-sm border-0 mb-3"><div class="card-body row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label small fw-semibold mb-1">Search</label><input name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Tracking no., subject, control no."></div>
    <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Status</label>
        <select name="status" class="form-select form-select-sm"><option value="">All</option><?php foreach (DOC_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Branch</label>
        <select name="branch" class="form-select form-select-sm"><option value="0">All</option><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $branch===(int)$b['id']?'selected':'' ?>><?= e($b['branch_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Current Holder</label>
        <select name="holder" class="form-select form-select-sm"><option value="0">All</option><?php foreach ($holders as $h): ?><option value="<?= (int)$h['id'] ?>" <?= $holder===(int)$h['id']?'selected':'' ?>><?= e($h['full_name']) ?> (<?= e(role_label($h['role'])) ?>)</option><?php endforeach; ?></select>
    </div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button><a href="monitoring.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
</div></form>

<div class="card shadow-sm border-0"><div class="card-body p-0">
<table class="table table-hover table-sm align-middle mb-0">
<thead class="table-light small"><tr>
    <th>Tracking No.</th><th>Subject</th><th>Origin</th><th>Current Holder</th><th>Role</th><th>Status</th><th>Age</th><th>Last Action</th><th>Updated</th><th></th>
</tr></thead>
<tbody>
<?php foreach ($rows as $r):
    $age = doc_age_days($r);
    $level = aging_level($age);
    $rowCls = $level === 'overdue' ? 'table-danger' : ($level === 'attention' ? 'table-warning' : '');
?>
<tr class="<?= $rowCls ?>">
    <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
    <td class="small text-truncate" style="max-width:180px" title="<?= e($r['subject']) ?>"><?= e($r['subject']) ?></td>
    <td class="small"><?= e($r['origin_branch'] ?? '—') ?></td>
    <td class="small"><?= e($r['holder_name'] ?? '—') ?></td>
    <td class="small"><?= e(role_label($r['holder_role'] ?? '')) ?></td>
    <td><?= status_badge($r['current_status']) ?></td>
    <td><?= aging_badge($age) ?></td>
    <td class="small text-uppercase"><?= e($r['last_action'] ?? '—') ?></td>
    <td class="small text-muted"><?= e(date('M d g:iA', strtotime($r['updated_at']))) ?></td>
    <td class="small"><a class="btn btn-sm btn-outline-primary py-0" href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>">View</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted small py-4">No documents found.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-end">
<?php for ($i=1;$i<=$totalPages;$i++): ?><li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= e($q) ?>&status=<?= e($status) ?>&branch=<?= (int)$branch ?>&holder=<?= (int)$holder ?>"><?= $i ?></a></li><?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php page_footer(); ?>
