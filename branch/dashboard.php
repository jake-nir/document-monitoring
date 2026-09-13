<?php
/**
 * Branch dashboard.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_BRANCH);

$uid = (int)$user['id'];
$pdo = db();

// Stats
$stmt = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE created_by = ?');
$stmt->execute([$uid]); $total = (int)$stmt->fetch()['c'];

foreach (['PENDING','SIGNED','APPROVED','RTS'] as $s) {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE created_by = ? AND current_status = ?');
    $stmt->execute([$uid, $s]); $$s = (int)$stmt->fetch()['c'];
}

// Currently with me (docs sent to me / held by me)
$stmt = $pdo->prepare('SELECT COUNT(*) c FROM documents WHERE current_holder_id = ?');
$stmt->execute([$uid]); $withMe = (int)$stmt->fetch()['c'];

// Recent documents I logged
$stmt = $pdo->prepare('SELECT d.*, b.branch_name AS current_branch_name FROM documents d LEFT JOIN branches b ON b.id=d.current_branch_id WHERE d.created_by = ? ORDER BY d.created_at DESC LIMIT 8');
$stmt->execute([$uid]); $recent = $stmt->fetchAll();

// Documents currently with me (incoming awaiting action)
$stmt = $pdo->prepare('SELECT d.*, b.branch_name AS origin_branch FROM documents d LEFT JOIN branches b ON b.id=d.originating_branch_id WHERE d.current_holder_id = ? ORDER BY d.updated_at DESC LIMIT 8');
$stmt->execute([$uid]); $mine = $stmt->fetchAll();

// Recently forwarded by me (last tracking = FORWARDED by this user)
$stmt = $pdo->prepare('SELECT d.tracking_number, d.subject, d.current_status, dt.to_user_id, dt.created_at AS fwd_at, u.full_name AS to_name FROM document_tracking dt JOIN documents d ON d.id=dt.document_id LEFT JOIN users u ON u.id=dt.to_user_id WHERE dt.from_user_id = ? AND dt.action = "FORWARDED" ORDER BY dt.created_at DESC LIMIT 6');
$stmt->execute([$uid]); $recentFwd = $stmt->fetchAll();

page_header('Branch Dashboard', 'dashboard', $user);
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0 text-bg-light"><div class="card-body"><div class="stat-num"><?= $total ?></div><div class="stat-label">Total Logged</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-warning-subtle"><div class="stat-num"><?= $PENDING ?></div><div class="stat-label text-warning-emphasis">Pending</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-primary-subtle"><div class="stat-num"><?= $SIGNED ?></div><div class="stat-label text-primary-emphasis">Signed</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-success-subtle"><div class="stat-num"><?= $APPROVED ?></div><div class="stat-label text-success-emphasis">Approved</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-danger-subtle"><div class="stat-num"><?= $RTS ?></div><div class="stat-label text-danger-emphasis">RTS</div></div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card stat-card shadow-sm border-0"><div class="card-body bg-info-subtle"><div class="stat-num"><?= $withMe ?></div><div class="stat-label text-info-emphasis">With Me</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-folder2-open me-2"></i>Recent Documents</span>
                <a href="<?= BASE_URL ?>/branch/documents.php" class="small">View all</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light small">
                        <tr><th>Tracking No.</th><th>Subject</th><th>Status</th><th>Logged</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
                            <td class="small text-truncate" style="max-width:200px"><?= e($r['subject']) ?></td>
                            <td><?= status_badge($r['current_status']) ?></td>
                            <td class="small text-muted"><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr><td colspan="4" class="text-muted small text-center py-3">No documents logged yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-inbox me-2"></i>Awaiting My Action</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light small"><tr><th>Tracking No.</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($mine as $m): ?>
                        <tr>
                            <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$m['id'] ?>"><?= e($m['tracking_number']) ?></a></td>
                            <td><?= status_badge($m['current_status']) ?></td>
                            <td class="small">
                                <?php if (awaiting_receipt((int)$m['id'], $uid)): ?>
                                    <a class="btn btn-sm btn-outline-success py-0" href="<?= BASE_URL ?>/branch/incoming.php"><i class="bi bi-inbox me-1"></i>Receive</a>
                                <?php else: ?>
                                    <a class="btn btn-sm btn-outline-primary py-0" href="<?= BASE_URL ?>/branch/forward.php?id=<?= (int)$m['id'] ?>">Forward</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$mine): ?><tr><td colspan="3" class="text-muted small text-center py-3">Nothing awaiting action.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-send me-2"></i>Recently Forwarded</div>
            <div class="card-body p-0">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light small"><tr><th>Tracking No.</th><th>To</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentFwd as $f): ?>
                        <tr><td class="small"><?= e($f['tracking_number']) ?></td><td class="small"><?= e($f['to_name'] ?? '—') ?></td><td class="small text-muted"><?= e(date('M d g:iA', strtotime($f['fwd_at']))) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$recentFwd): ?><tr><td colspan="3" class="text-muted small text-center py-3">No documents forwarded yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php page_footer(); ?>
