<?php
/**
 * Branch: Forward a document to an authorized destination.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_BRANCH);
$uid = (int)$user['id'];

$id = (int)($_GET['id'] ?? 0);
$doc = $id > 0 ? document_with_relations($id) : null;

// CORE OWNERSHIP RULE: only the CURRENT holder may forward a document.
// Being the originator/creator of the document grants NO forwarding rights
// while the document is in someone else's possession.
if (!$doc || !is_current_holder($doc, $uid)) {
    set_flash('danger', 'You cannot perform this action because this document is no longer assigned to you.');
    redirect_by_role(ROLE_BRANCH);
}
if (awaiting_receipt($id, $uid)) {
    set_flash('danger', 'This document is awaiting your confirmation. Please receive it first before forwarding.');
    redirect_by_role(ROLE_BRANCH);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $toUserId = (int)($_POST['to_user'] ?? 0);
    $remarks = trim((string)($_POST['remarks'] ?? ''));

    if ($toUserId <= 0) { $errors[] = 'Please select a destination user.'; }
    elseif ($toUserId === $uid) { $errors[] = 'You cannot forward a document to yourself.'; }

    if (!$errors) {
        $toUser = fetch_user($toUserId);
        if (!$toUser || $toUser['status'] !== 'active' || !$toUser['branch_id']) {
            $errors[] = 'Invalid destination user.';
        } elseif (!can_route(ROLE_BRANCH, $toUser['role'])) {
            $errors[] = 'Unauthorized routing destination. This user role cannot receive from a Branch.';
        } else {
            try {
                forward_document($id, $uid, $toUserId, $remarks);
                set_flash('success', $doc['tracking_number'] . ' forwarded to ' . $toUser['full_name'] . '.');
                header('Location: ' . BASE_URL . '/view_document.php?id=' . $id);
                exit;
            } catch (Throwable $e) {
                log_error('branch forward: ' . $e->getMessage());
                $errors[] = 'Something went wrong. Please try again.';
            }
        }
    }
}

// Destination users eligible for Branch role
$destUsers = [];
foreach (route_targets(ROLE_BRANCH) as $r) {
    $stmt = db()->prepare('SELECT u.id, u.full_name, u.position, u.role, b.branch_name FROM users u LEFT JOIN branches b ON b.id=u.branch_id WHERE u.role=? AND u.status="active" AND u.branch_id IS NOT NULL AND u.id <> ? ORDER BY u.full_name');
    $stmt->execute([$r, $uid]);
    foreach ($stmt->fetchAll() as $u) { $destUsers[] = $u; }
}

page_header('Forward Document', 'documents', $user);
?>
<h4 class="mb-3"><i class="bi bi-send me-2"></i>Forward Document</h4>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body small">
        <div class="row">
            <div class="col-md-3"><span class="text-muted">Tracking No.:</span> <strong><?= e($doc['tracking_number']) ?></strong></div>
            <div class="col-md-6"><span class="text-muted">Subject:</span> <?= e($doc['subject']) ?></div>
            <div class="col-md-3"><span class="text-muted">Current Status:</span> <?= status_badge($doc['current_status']) ?></div>
        </div>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger small"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="card shadow-sm border-0" id="forwardForm">
    <div class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="doc_id" value="<?= (int)$id ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Forward To <span class="text-danger">*</span></label>
                <select name="to_user" id="toUser" class="form-select" required>
                    <option value="">-- Select user --</option>
                    <?php foreach ($destUsers as $d): ?>
                        <option data-branch="<?= e($d['branch_name'] ?? '') ?>" value="<?= (int)$d['id'] ?>"><?= e($d['full_name']) ?> (<?= e(role_label($d['role'])) ?><?= $d['branch_name'] ? ' - ' . e($d['branch_name']) : '' ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Destination Branch/Office</label>
                <input type="text" id="destBranch" class="form-control" readonly placeholder="Auto-populated">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold small">Remarks</label>
                <textarea name="remarks" class="form-control" rows="3"><?= e($_POST['remarks'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-light d-flex justify-content-end gap-2">
        <a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
        <button type="submit" class="btn btn-primary btn-sm" id="forwardBtn"><i class="bi bi-send me-1"></i>Forward Document</button>
    </div>
</form>
<?php page_footer(); ?>
<script>
document.getElementById('toUser').addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    document.getElementById('destBranch').value = opt.getAttribute('data-branch') || '';
});
document.getElementById('forwardForm').addEventListener('submit', function (e) {
    var nameSel = document.getElementById('toUser');
    var toName = nameSel.options[nameSel.selectedIndex] ? nameSel.options[nameSel.selectedIndex].text : '';
    var track = '<?= e($doc['tracking_number']) ?>';
    if (!confirm('Are you sure you want to forward ' + track + ' to ' + toName + '?')) { e.preventDefault(); }
});
</script>
