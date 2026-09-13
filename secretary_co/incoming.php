<?php
/**
 * Secretary of CO: Incoming documents (awaiting receive) + RECEIVE action.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_SECRETARY_CO);
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive'])) {
    verify_csrf();
    $id = (int)($_POST['doc_id'] ?? 0);
    $doc = $id > 0 ? fetch_document($id) : null;
    if ($doc && (int)$doc['current_holder_id'] === $uid && awaiting_receipt($id, $uid)) {
        try {
            receive_document($id, $uid);
            set_flash('success', $doc['tracking_number'] . ' received successfully.');
        } catch (Throwable $e) {
            log_error('receive: ' . $e->getMessage());
            set_flash('danger', 'Could not receive the document. ' . $e->getMessage());
        }
    } else {
        set_flash('danger', 'Invalid document or not addressed to you.');
    }
    header('Location: ' . BASE_URL . '/secretary_co/incoming.php');
    exit;
}

$pdo = db();


$rows = incoming_documents($uid);

page_header('Incoming Documents', 'incoming', $user);
?>
<h4 class="mb-3"><i class="bi bi-inbox me-2"></i>Incoming Documents</h4>
<div class="card shadow-sm border-0"><div class="card-body p-0">
<table class="table table-hover table-sm align-middle mb-0">
<thead class="table-light small"><tr><th>Tracking No.</th><th>Subject</th><th>Origin</th><th>Status</th><th>Forwarded</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
    <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
    <td class="small"><?= e($r['subject']) ?></td>
    <td class="small"><?= e($r['origin_branch'] ?? '—') ?></td>
    <td><?= status_badge($r['current_status']) ?></td>
    <td class="small text-muted"><?= e(date('M d, Y g:iA', strtotime($r['updated_at']))) ?></td>
    <td class="small">
        <form method="post" class="d-inline"><?= csrf_field() ?>
            <input type="hidden" name="doc_id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-success py-0" name="receive" value="1" onclick="return confirm('Are you sure you want to receive <?= e($r['tracking_number']) ?>?')"><i class="bi bi-inbox me-1"></i>Receive</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted small py-4">No incoming documents.</td></tr><?php endif; ?>
</tbody></table>
</div></div>
<?php page_footer(); ?>
