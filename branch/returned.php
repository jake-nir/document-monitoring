<?php
/**
 * Branch: Returned documents (RTS) that belong to this user's branch.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_BRANCH);
$uid = (int)$user['id'];
$branchId = (int)$user['branch_id'];

$pdo = db();
$stmt = $pdo->prepare("SELECT d.*, b.branch_name AS origin_branch, cu.full_name AS holder_name FROM documents d
    LEFT JOIN branches b ON b.id=d.originating_branch_id
    LEFT JOIN users cu ON cu.id=d.current_holder_id
    WHERE d.originating_branch_id=? AND d.current_status='RTS' ORDER BY d.updated_at DESC LIMIT 50");
$stmt->execute([$branchId]);
$rows = $stmt->fetchAll();

page_header('Returned Documents', 'returned', $user);
?>
<h4 class="mb-3"><i class="bi bi-arrow-return-left me-2"></i>Returned Documents</h4>
<div class="card shadow-sm border-0"><div class="card-body p-0">
<table class="table table-hover table-sm align-middle mb-0">
<thead class="table-light small"><tr><th>Tracking No.</th><th>Subject</th><th>Returned From</th><th>Status</th><th>Updated</th><th></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
    <td class="small"><a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"><?= e($r['tracking_number']) ?></a></td>
    <td class="small"><?= e($r['subject']) ?></td>
    <td class="small"><?= e($r['holder_name'] ?? '—') ?></td>
    <td><?= status_badge($r['current_status']) ?></td>
    <td class="small text-muted"><?= e(date('M d, Y g:iA', strtotime($r['updated_at']))) ?></td>
    <td class="small text-end"><a class="btn btn-sm btn-outline-primary py-0" href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>">View</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted small py-4">No returned documents.</td></tr><?php endif; ?>
</tbody></table>
</div></div>
<?php page_footer(); ?>
