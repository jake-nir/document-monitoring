<?php
/**
 * Secretary Deputy: My Documents (held by me) with search/filter.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_SECRETARY_CO);
$uid = (int)$user['id'];

$q = trim((string)($_GET['q'] ?? ''));
$status = $_GET['status'] ?? '';
$limit = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = ['d.current_holder_id = ?'];
$params = [$uid];
if ($q !== '') {
    $where[] = '(d.tracking_number LIKE ? OR d.subject LIKE ? OR d.document_type LIKE ?)';
    $like = "%$q%"; array_push($params, $like, $like, $like);
}
if ($status !== '') { $where[] = 'd.current_status = ?'; $params[] = $status; }
$whereSql = implode(' AND ', $where);

$pdo = db();
$stmt = $pdo->prepare("SELECT COUNT(*) c FROM documents d WHERE $whereSql");
$stmt->execute($params); $totalRows = (int)$stmt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalRows / $limit));

$stmt = $pdo->prepare("SELECT d.*, b.branch_name AS origin_branch FROM documents d LEFT JOIN branches b ON b.id=d.originating_branch_id WHERE $whereSql ORDER BY d.updated_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params); $rows = $stmt->fetchAll();

page_header('My Documents', 'documents', $user);
?>
<h4 class="mb-3"><i class="bi bi-folder2-open me-2"></i>My Documents</h4>
<form method="get" class="card shadow-sm border-0 mb-3"><div class="card-body row g-2 align-items-end">
    <div class="col-md-6"><label class="form-label small fw-semibold mb-1">Search</label><input name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Tracking no., subject, type..."></div>
    <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Status</label>
        <select name="status" class="form-select form-select-sm"><option value="">All</option><?php foreach (DOC_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-3 d-flex gap-2 align-items-end"><button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button><a href="documents.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
</div></form>

<div class="card shadow-sm border-0"><div class="card-body p-0">
<table class="table table-hover table-sm align-middle mb-0">
<thead class="table-light small"><tr><th>Tracking No.</th><th>Subject</th><th>Origin</th><th>Status</th><th>Age</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
    <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
    <td class="small text-truncate" style="max-width:220px"><?= e($r['subject']) ?></td>
    <td class="small"><?= e($r['origin_branch'] ?? '—') ?></td>
    <td><?= status_badge($r['current_status']) ?></td>
    <td><?= aging_badge(doc_age_days($r)) ?></td>
    <td class="small text-end"><a class="btn btn-sm btn-outline-primary py-0" href="<?= BASE_URL ?>/secretary_co/process.php?id=<?= (int)$r['id'] ?>">Process</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted small py-4">No documents.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-end">
<?php for ($i=1;$i<=$totalPages;$i++): ?><li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= e($q) ?>&status=<?= e($status) ?>"><?= $i ?></a></li><?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php page_footer(); ?>
