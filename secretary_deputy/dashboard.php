<?php
/**
 * Secretary Deputy dashboard.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_SECRETARY_DEPUTY);
$uid = (int)$user['id'];

$pdo = db();

// Incoming = docs forwarded to me, awaiting receipt
$incoming = incoming_count($uid);


// With me
$stmt = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE current_holder_id = ?');
$stmt->execute([$uid]); $withMe = (int)$stmt->fetch()['c'];

foreach (['PENDING','SIGNED','APPROVED','RTS'] as $s) {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE current_holder_id = ? AND current_status = ?');
    $stmt->execute([$uid, $s]); $$s = (int)$stmt->fetch()['c'];
}

// Incoming list (awaiting receive)
$incomingList = incoming_documents($uid);


// Awaiting action (received, held by me)
$awaiting = my_documents($uid);


// Recently processed (my tracking actions)
$stmt = $pdo->prepare("SELECT d.tracking_number, d.subject, dt.action, dt.created_at FROM document_tracking dt JOIN documents d ON d.id=dt.document_id WHERE dt.from_user_id=? OR dt.to_user_id=? ORDER BY dt.created_at DESC LIMIT 6");
$stmt->execute([$uid, $uid]); $recent = $stmt->fetchAll();

page_header('Secretary Deputy Dashboard', 'dashboard', $user);
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-info-subtle"><div class="stat-num"><?= $incoming ?></div><div class="stat-label text-info-emphasis">Incoming</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-warning-subtle"><div class="stat-num"><?= $PENDING ?></div><div class="stat-label text-warning-emphasis">Pending</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-primary-subtle"><div class="stat-num"><?= $SIGNED ?></div><div class="stat-label text-primary-emphasis">Signed</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-success-subtle"><div class="stat-num"><?= $APPROVED ?></div><div class="stat-label text-success-emphasis">Approved</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-danger-subtle"><div class="stat-num"><?= $RTS ?></div><div class="stat-label text-danger-emphasis">RTS</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-secondary-subtle"><div class="stat-num"><?= $withMe ?></div><div class="stat-label">With Me</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-inbox me-2"></i>Incoming Documents</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0"><thead class="table-light small"><tr><th>Tracking</th><th>Status</th><th></th></tr></thead><tbody>
                <?php foreach ($incomingList as $r): ?>
                    <tr>
                        <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
                        <td><?= status_badge($r['current_status']) ?></td>
                        <td class="small"><a class="btn btn-sm btn-outline-success py-0" href="<?= BASE_URL ?>/secretary_deputy/incoming.php">Receive</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$incomingList): ?><tr><td colspan="3" class="text-muted small text-center py-3">No incoming.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-pencil-square me-2"></i>Awaiting Action</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0"><thead class="table-light small"><tr><th>Tracking</th><th>Status</th><th></th></tr></thead><tbody>
                <?php foreach ($awaiting as $r): ?>
                    <tr>
                        <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
                        <td><?= status_badge($r['current_status']) ?></td>
                        <td class="small"><a class="btn btn-sm btn-outline-primary py-0" href="<?= BASE_URL ?>/secretary_deputy/process.php?id=<?= (int)$r['id'] ?>">Process</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$awaiting): ?><tr><td colspan="3" class="text-muted small text-center py-3">Nothing awaiting action.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-clock-history me-2"></i>Recently Processed</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0"><thead class="table-light small"><tr><th>Tracking</th><th>Action</th><th>When</th></tr></thead><tbody>
                <?php foreach ($recent as $r): ?>
                    <tr><td class="small"><?= e($r['tracking_number']) ?></td><td class="small text-uppercase"><?= e($r['action']) ?></td><td class="small text-muted"><?= e(date('M d g:iA', strtotime($r['created_at']))) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?><tr><td colspan="3" class="text-muted small text-center py-3">No recent activity.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </div>
    </div>
</div>
<?php page_footer(); ?>
