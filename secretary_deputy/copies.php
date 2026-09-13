<?php
/**
 * Secretary Deputy: My Copies (personal copies of processed documents).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_SECRETARY_DEPUTY);
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $docId = (int)($_POST['doc_id'] ?? 0);
    if (isset($_POST['remove_copy']) && $docId > 0) {
        remove_document_copy($docId, $uid);
        set_flash('success', 'Copy removed.');
    }
    header('Location: ' . BASE_URL . '/secretary_deputy/copies.php');
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$copies = saved_copies($uid, $q);

page_header('My Copies', 'copies', $user);
?>
<h4 class="mb-3"><i class="bi bi-clipboard-data me-2"></i>My Copies</h4>
<p class="text-muted small">Personal copies of documents you have processed. Removing a copy only removes it from this list; the original document is unaffected.</p>

<form method="get" class="card shadow-sm border-0 mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-8 col-lg-9">
            <label class="form-label small fw-semibold mb-1">Search Copies</label>
            <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Tracking no., subject, type, sender...">
        </div>
        <div class="col-md-4 col-lg-3 d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search me-1"></i>Search</button>
            <a href="<?= BASE_URL ?>/secretary_deputy/copies.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </div>
</form>

<div class="card shadow-sm border-0"><div class="card-body p-0">
<table class="table table-hover table-sm align-middle mb-0">
<thead class="table-light small"><tr><th>Tracking No.</th><th>Subject</th><th>Origin</th><th>Status</th><th>Saved On</th><th></th></tr></thead>
<tbody>
<?php foreach ($copies as $r): ?>
<tr>
    <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
    <td class="small text-truncate" style="max-width:220px"><?= e($r['subject']) ?></td>
    <td class="small"><?= e($r['origin_branch'] ?? '—') ?></td>
    <td><?= status_badge($r['current_status']) ?></td>
    <td class="small"><?= date('M j, Y H:i', strtotime($r['saved_at'])) ?></td>
    <td class="small text-end">
        <a class="btn btn-sm btn-outline-primary py-0" href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>">View</a>
        <form method="post" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="doc_id" value="<?= (int)$r['id'] ?>">
            <button type="submit" name="remove_copy" value="1" class="btn btn-sm btn-outline-danger py-0">Remove</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if (!$copies): ?><tr><td colspan="6" class="text-center text-muted small py-4"><?= $q !== '' ? 'No saved copies match your search.' : 'No saved copies yet. Open a document you have processed and click <strong>Save Copy</strong>.' ?></td></tr><?php endif; ?>
</tbody></table>
</div></div>

<?php page_footer(); ?>