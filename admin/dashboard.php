<?php
/**
 * Admin dashboard with summary cards and Chart.js charts.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_ADMIN);

$pdo = db();

$total   = (int)$pdo->query('SELECT COUNT(*) c FROM documents')->fetch()['c'];
$pending = (int)$pdo->query("SELECT COUNT(*) c FROM documents WHERE current_status='PENDING'")->fetch()['c'];
$signed  = (int)$pdo->query("SELECT COUNT(*) c FROM documents WHERE current_status='SIGNED'")->fetch()['c'];
$approved= (int)$pdo->query("SELECT COUNT(*) c FROM documents WHERE current_status='APPROVED'")->fetch()['c'];
$rts     = (int)$pdo->query("SELECT COUNT(*) c FROM documents WHERE current_status='RTS'")->fetch()['c'];
$activeUsers   = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE status='active'")->fetch()['c'];
$activeBranches= (int)$pdo->query("SELECT COUNT(*) c FROM branches WHERE status='active'")->fetch()['c'];

// By holder role
$byHolderRole = [];
foreach ($pdo->query("SELECT u.role, COUNT(d.id) AS c FROM documents d LEFT JOIN users u ON u.id=d.current_holder_id GROUP BY u.role") as $r) {
    $byHolderRole[role_label((string)$r['role'])] = (int)$r['c'];
}

// By status (for pie chart)
$statusData = [];
foreach (['PENDING','SIGNED','APPROVED','RTS','RECEIVED','NEW'] as $s) {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM documents WHERE current_status=?");
    $st->execute([$s]); $statusData[$s] = (int)$st->fetch()['c'];
}

// By branch (originating)
$byBranch = [];
foreach ($pdo->query("SELECT b.branch_name, COUNT(d.id) AS c FROM documents d LEFT JOIN branches b ON b.id=d.originating_branch_id GROUP BY b.id") as $r) {
    $byBranch[$r['branch_name']] = (int)$r['c'];
}

// Received per day (last 14 days)
$perDay = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $st = $pdo->prepare("SELECT COUNT(*) c FROM documents WHERE DATE(created_at)=?");
    $st->execute([$day]); $perDay[date('M d', strtotime($day))] = (int)$st->fetch()['c'];
}

// Recently moved
$recent = $pdo->query("SELECT d.tracking_number, d.subject, dt.action, dt.created_at, u.full_name AS actor FROM document_tracking dt JOIN documents d ON d.id=dt.document_id LEFT JOIN users u ON u.id=dt.from_user_id ORDER BY dt.created_at DESC LIMIT 10")->fetchAll();

page_header('Admin Dashboard', 'dashboard', $user, ['/public/assets/chartjs/chart.umd.min.js']);
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0 bg-primary-subtle"><div class="card-body"><div class="stat-num"><?= $total ?></div><div class="stat-label">Total Documents</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0 bg-warning-subtle"><div class="card-body"><div class="stat-num"><?= $pending ?></div><div class="stat-label text-warning-emphasis">Pending</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0 bg-primary-subtle"><div class="card-body"><div class="stat-num"><?= $signed ?></div><div class="stat-label">Signed</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0 bg-success-subtle"><div class="card-body"><div class="stat-num"><?= $approved ?></div><div class="stat-label text-success-emphasis">Approved</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0 bg-danger-subtle"><div class="card-body"><div class="stat-num"><?= $rts ?></div><div class="stat-label text-danger-emphasis">RTS</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0 bg-secondary-subtle"><div class="card-body"><div class="stat-num"><?= $activeUsers ?></div><div class="stat-label">Active Users</div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5 col-xl-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-pie-chart me-2"></i>Documents by Status</div>
            <div class="card-body"><canvas id="statusChart" height="220"></canvas></div>
        </div>
    </div>
    <div class="col-lg-7 col-xl-8">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-bar-chart me-2"></i>Documents Received (last 14 days)</div>
            <div class="card-body"><canvas id="dayChart" height="220"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5 col-xl-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-diagram-3 me-2"></i>By Current Holder Role</div>
            <div class="card-body"><canvas id="holderChart" height="220"></canvas></div>
        </div>
    </div>
    <div class="col-lg-7 col-xl-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-building me-2"></i>Documents by Branch</div>
            <div class="card-body"><canvas id="branchChart" height="220"></canvas></div>
        </div>
    </div>
    <div class="col-lg-12 col-xl-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-activity me-2"></i>Recently Moved Documents</div>
            <div class="card-body p-2">
                <ul class="list-group list-group-flush small">
                <?php foreach ($recent as $r): ?>
                    <li class="list-group-item px-2 py-1 d-flex justify-content-between align-items-center">
                        <span><?= e($r['tracking_number']) ?> <span class="text-muted text-uppercase">· <?= e($r['action']) ?></span></span>
                        <span class="text-muted small"><?= e(date('M d g:iA', strtotime($r['created_at']))) ?></span>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
window.dms_charts = {
    status: <?= json_encode(array_values($statusData)) ?>,
    statusLabels: <?= json_encode(array_keys($statusData)) ?>,
    days: <?= json_encode(array_values($perDay)) ?>,
    dayLabels: <?= json_encode(array_keys($perDay)) ?>,
    holder: <?= json_encode(array_values($byHolderRole)) ?>,
    holderLabels: <?= json_encode(array_keys($byHolderRole)) ?>,
    branch: <?= json_encode(array_values($byBranch)) ?>,
    branchLabels: <?= json_encode(array_keys($byBranch)) ?>
};
</script>
<?php page_footer(); ?>
