<?php
/**
 * Monitoring Reports: filterable summaries + document listing, print-friendly.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();

$pdo = db();

$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$branch = (int)($_GET['branch'] ?? 0);
$status = $_GET['status'] ?? '';
$holder = (int)($_GET['holder'] ?? 0);

$where = ['1=1'];
$params = [];
if ($from !== '') { $where[] = 'DATE(d.created_at) >= ?'; $params[] = $from; }
if ($to !== '')   { $where[] = 'DATE(d.created_at) <= ?'; $params[] = $to; }
if ($branch > 0)  { $where[] = 'd.originating_branch_id = ?'; $params[] = $branch; }
if ($status !== '') { $where[] = 'd.current_status = ?'; $params[] = $status; }
if ($holder > 0)  { $where[] = 'd.current_holder_id = ?'; $params[] = $holder; }
$whereSql = implode(' AND ', $where);

// Detail listing
$stmt = $pdo->prepare("SELECT d.*, ob.branch_name AS origin_branch, b.branch_name AS current_branch, cu.full_name AS holder_name, cr.full_name AS created_name
    FROM documents d
    LEFT JOIN branches ob ON ob.id=d.originating_branch_id
    LEFT JOIN branches b ON b.id=d.current_branch_id
    LEFT JOIN users cu ON cu.id=d.current_holder_id
    LEFT JOIN users cr ON cr.id=d.created_by
    WHERE $whereSql ORDER BY d.created_at DESC LIMIT 500");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Aggregates
$stmt = $pdo->prepare("SELECT COUNT(*) c FROM documents d WHERE $whereSql"); $stmt->execute($params);
$totalCount = (int)$stmt->fetch()['c'];
$byStatus = [];
foreach (DOC_STATUSES as $s) {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM documents d WHERE $whereSql AND d.current_status=?"); $st->execute(array_merge($params, [$s]));
    $byStatus[$s] = (int)$st->fetch()['c'];
}
$byBranch = [];
$st = $pdo->prepare("SELECT ob.branch_name, COUNT(*) c FROM documents d LEFT JOIN branches ob ON ob.id=d.originating_branch_id WHERE $whereSql GROUP BY ob.id ORDER BY c DESC");
$st->execute($params); foreach ($st->fetchAll() as $r) { $byBranch[$r['branch_name'] ?? 'Unknown'] = (int)$r['c']; }

$branches = $pdo->query('SELECT * FROM branches ORDER BY branch_name')->fetchAll();
$holders = $pdo->query("SELECT u.id, u.full_name, u.role FROM users u WHERE u.status='active' AND u.role IN ('BRANCH','SECRETARY_DEPUTY','SECRETARY_CO') ORDER BY u.full_name")->fetchAll();

$isAdmin = $user['role'] === ROLE_ADMIN;
$dashDir = ($user['role'] === ROLE_SECRETARY_CO) ? 'secretary_co' : (($user['role'] === ROLE_SECRETARY_DEPUTY) ? 'secretary_deputy' : strtolower($user['role']));

page_header('Monitoring Reports', 'reports', $user);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Monitoring Reports</h4>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<form method="get" class="card shadow-sm border-0 mb-3"><div class="card-body row g-2 align-items-end">
    <div class="col-6 col-md-2"><label class="form-label small fw-semibold mb-1">Date From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small fw-semibold mb-1">Date To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small fw-semibold mb-1">Branch</label>
        <select name="branch" class="form-select form-select-sm"><option value="0">All</option><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $branch===(int)$b['id']?'selected':'' ?>><?= e($b['branch_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-6 col-md-2"><label class="form-label small fw-semibold mb-1">Status</label>
        <select name="status" class="form-select form-select-sm"><option value="">All</option><?php foreach (DOC_STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-6 col-md-2"><label class="form-label small fw-semibold mb-1">Holder</label>
        <select name="holder" class="form-select form-select-sm"><option value="0">All</option><?php foreach ($holders as $h): ?><option value="<?= (int)$h['id'] ?>" <?= $holder===(int)$h['id']?'selected':'' ?>><?= e($h['full_name']) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-6 col-md-2 d-flex gap-2 align-items-end"><button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Run Report</button><a href="index.php" class="btn btn-outline-secondary btn-sm">Reset</a></div>
</div></form>

<div class="row g-3 mb-3">
    <div class="col-md-6 col-xl-3"><div class="card stat-card shadow-sm border-0 bg-primary-subtle"><div class="card-body"><div class="stat-num"><?= $totalCount ?></div><div class="stat-label">Total Documents</div></div></div></div>
    <?php foreach (['PENDING'=>'warning','SIGNED'=>'primary','APPROVED'=>'success','RTS'=>'danger'] as $s=>$tone): ?>
    <div class="col-md-6 col-xl-2"><div class="card stat-card shadow-sm border-0 bg-<?= $tone ?>-subtle"><div class="card-body"><div class="stat-num"><?= $byStatus[$s] ?? 0 ?></div><div class="stat-label text-<?= $tone ?>-emphasis"><?= $s ?></div></div></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-semibold">Documents by Status</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <?php foreach ($byStatus as $s => $c): ?>
                    <tr><td class="small"><?= status_badge($s) ?></td><td class="text-end fw-semibold"><?= $c ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-semibold">Documents by Branch</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <?php if ($byBranch): foreach ($byBranch as $name => $c): ?>
                    <tr><td class="small"><?= e($name) ?></td><td class="text-end fw-semibold"><?= $c ?></td></tr>
                    <?php endforeach; else: ?><tr><td class="small text-muted text-center py-3">No data.</td></tr><?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 report-table">
    <div class="card-header bg-light fw-semibold">Document Detail Report (<?= count($rows) ?> records)</div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light small"><tr><th>Tracking No.</th><th>Subject</th><th>Origin</th><th>Created By</th><th>Current Holder</th><th>Status</th><th>Priority</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
                <td class="small text-truncate" style="max-width:200px"><?= e($r['subject']) ?></td>
                <td class="small"><?= e($r['origin_branch'] ?? '—') ?></td>
                <td class="small"><?= e($r['created_name'] ?? '—') ?></td>
                <td class="small"><?= e($r['holder_name'] ?? '—') ?></td>
                <td><?= status_badge($r['current_status']) ?></td>
                <td><?= priority_badge($r['priority']) ?></td>
                <td class="small text-muted"><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted small py-4">No documents match the selected criteria.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_footer(); ?>
