<?php
/**
 * Branch: My Documents (search, filter, paginated).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_BRANCH);
$uid = (int)$user['id'];

$q   = trim((string)($_GET['q'] ?? ''));
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$limit = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$where = ['d.created_by = ?'];
$params = [$uid];

if ($q !== '') {
    $where[] = '(d.tracking_number LIKE ? OR d.subject LIKE ? OR d.doc_control_number LIKE ? OR d.document_type LIKE ? OR d.sender LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($status !== '') { $where[] = 'd.current_status = ?'; $params[] = $status; }
if ($priority !== '') { $where[] = 'd.priority = ?'; $params[] = $priority; }
if ($from !== '') { $where[] = 'DATE(d.created_at) >= ?'; $params[] = $from; }
if ($to !== '') { $where[] = 'DATE(d.created_at) <= ?'; $params[] = $to; }

$whereSql = implode(' AND ', $where);

$pdo = db();
$stmt = $pdo->prepare("SELECT COUNT(*) c FROM documents d WHERE $whereSql");
$stmt->execute($params);
$totalRows = (int)$stmt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalRows / $limit));

$stmt = $pdo->prepare("SELECT d.*, ob.branch_name AS origin_branch, cu.full_name AS holder_name FROM documents d
    LEFT JOIN branches ob ON ob.id=d.originating_branch_id
    LEFT JOIN users cu ON cu.id=d.current_holder_id
    WHERE $whereSql ORDER BY d.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

page_header('My Documents', 'documents', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">My Documents</h4>
    <a href="<?= BASE_URL ?>/branch/create_document.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Log Document</a>
</div>

<form method="get" class="card shadow-sm border-0 mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small fw-semibold mb-1">Search</label>
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Tracking no., subject, type, sender...">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach (DOC_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Priority</label>
            <select name="priority" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach (['Low','Normal','High','Urgent'] as $p): ?><option value="<?= e($p) ?>" <?= $priority===$p?'selected':'' ?>><?= e($p) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">From</label>
            <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">To</label>
            <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Search</button>
            <a href="<?= BASE_URL ?>/branch/documents.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light small">
                <tr><th>Tracking No.</th><th>Subject</th><th>Origin</th><th>Current Holder</th><th>Status</th><th>Date</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
                    <td class="small text-truncate" style="max-width:220px"><?= e($r['subject']) ?></td>
                    <td class="small"><?= e($r['origin_branch'] ?? '—') ?></td>
                    <td class="small"><?= e($r['holder_name'] ?? '—') ?></td>
                    <td><?= status_badge($r['current_status']) ?></td>
                    <td class="small text-muted"><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
                    <td class="small text-end">
                        <a class="btn btn-sm btn-outline-primary py-0" href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>">View</a>
                        <?php if ((int)$r['current_holder_id'] === $uid && !awaiting_receipt((int)$r['id'], $uid)): ?>
                            <a class="btn btn-sm btn-outline-secondary py-0" href="<?= BASE_URL ?>/branch/forward.php?id=<?= (int)$r['id'] ?>">Forward</a>
                        <?php else: ?>
                            <span class="badge bg-secondary small" title="Viewing only - document is currently with <?= e($r['holder_name'] ?? 'another user') ?>">View Only</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted small py-4">No documents found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm justify-content-end">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= e($q) ?>&status=<?= e($status) ?>&priority=<?= e($priority) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php page_footer(); ?>
