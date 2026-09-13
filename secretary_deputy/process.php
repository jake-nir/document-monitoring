<?php
/**
 * Secretary Deputy: Process document (receive / status / forward / RTS).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_SECRETARY_DEPUTY);
$uid = (int)$user['id'];
$secDir = 'secretary_deputy';

// Process any submitted action
$result = handle_document_actions($user);
if ($result['error']) {
    set_flash('danger', $result['error']);
    if (!empty($_POST['doc_id'])) {
        header('Location: ' . BASE_URL . '/' . $secDir . '/process.php?id=' . (int)$_POST['doc_id']);
        exit;
    }
} elseif ($result['redirect']) {
    set_flash('success', 'Document processed successfully.');
    header('Location: ' . $result['redirect']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$doc = $id > 0 ? document_with_relations($id) : null;
if (!$doc || (int)$doc['current_holder_id'] !== $uid) {
    set_flash('danger', 'Document not found or not assigned to you.');
    redirect_by_role(ROLE_SECRETARY_DEPUTY);
}

// Destination users for forwarding (based on this role's routing rules)
$destUsers = [];
foreach (route_targets($user['role']) as $r) {
    $stmt = db()->prepare('SELECT u.id, u.full_name, u.position, u.role, b.branch_name FROM users u LEFT JOIN branches b ON b.id=u.branch_id WHERE u.role=? AND u.status="active" AND u.branch_id IS NOT NULL AND u.id <> ? ORDER BY u.full_name');
    $stmt->execute([$r, $uid]);
    foreach ($stmt->fetchAll() as $u) { $destUsers[] = $u; }
}

$isInTransit = awaiting_receipt($id, $uid);

page_header('Process: ' . $doc['tracking_number'], 'process', $user);
?>
<h4 class="mb-3"><i class="bi bi-arrow-left-right me-2"></i>Process Document</h4>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body small">
        <div class="row">
            <div class="col-md-3"><span class="text-muted">Tracking No.:</span> <strong><?= e($doc['tracking_number']) ?></strong></div>
            <div class="col-md-4"><span class="text-muted">Subject:</span> <?= e($doc['subject']) ?></div>
            <div class="col-md-2"><span class="text-muted">Status:</span> <?= status_badge($doc['current_status']) ?></div>
            <div class="col-md-3"><span class="text-muted">From:</span> <?= e($doc['created_by_name'] ?? '—') ?></div>
        </div>
        <div class="row mt-2">
            <div class="col-md-12"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$id ?>" class="btn btn-sm btn-outline-primary py-0"><i class="bi bi-clock-history me-1"></i>View Full History</a></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Receive (if in transit) -->
    <?php if ($isInTransit): ?>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-success-subtle fw-semibold"><i class="bi bi-inbox me-2"></i>Receive Document</div>
            <div class="card-body text-center">
                <p class="small text-muted">Confirm receipt of this document.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="doc_id" value="<?= (int)$id ?>">
                    <button class="btn btn-success" name="action" value="receive" onclick="return confirm('Are you sure you want to receive <?= e($doc['tracking_number']) ?>?')"><i class="bi bi-check-lg me-1"></i>Receive</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Update status -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-warning-subtle fw-semibold"><i class="bi bi-pencil-square me-2"></i>Update Status</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="doc_id" value="<?= (int)$id ?>">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">New Status</label>
                        <select name="new_status" class="form-select form-select-sm" required>
                            <option value="">-- Select --</option>
                            <option value="PENDING">PENDING</option>
                            <option value="SIGNED">SIGNED</option>
                            <option value="APPROVED">APPROVED</option>
                            <option value="RTS">RTS - Return to Sender</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Remarks <span class="text-danger small">(required for RTS)</span></label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <button class="btn btn-warning btn-sm w-100" name="action" value="change_status" onclick="return confirm('Are you sure you want to update the status of <?= e($doc['tracking_number']) ?>?')">Update Status</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Forward -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-primary-subtle fw-semibold"><i class="bi bi-send me-2"></i>Forward</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="doc_id" value="<?= (int)$id ?>">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Forward To</label>
                        <select name="to_user" class="form-select form-select-sm" required>
                            <option value="">-- Select user --</option>
                            <?php foreach ($destUsers as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= e($d['full_name']) ?> (<?= e(role_label($d['role'])) ?><?= $d['branch_name'] ? ' - ' . e($d['branch_name']) : '' ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Remarks</label>
                        <textarea name="remarks" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm w-100" name="action" value="forward" onclick="return confirm('Are you sure you want to forward <?= e($doc['tracking_number']) ?> to the selected user?')"><i class="bi bi-send me-1"></i>Forward</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php page_footer(); ?>