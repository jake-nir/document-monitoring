<?php
/**
 * Shared document details + monitoring timeline page.
 * Accessible by all logged-in roles; renders role-appropriate actions.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login();
$role = $user['role'];

$id = (int)($_GET['id'] ?? 0);
$doc = $id > 0 ? document_with_relations($id) : null;
if (!$doc) {
    set_flash('danger', 'Document not found.');
    redirect_by_role($role);
}

// Fetch full timeline (tracking + status changes merged by time)
$tracking = db()->prepare('SELECT * FROM document_tracking WHERE document_id = ? ORDER BY created_at ASC, id ASC');
$tracking->execute([$id]);
$events = $tracking->fetchAll();

$processing = processing_records($id);
$permission = user_document_permission($doc, (int)$user['id']);

// Secretaries may save/remove a personal copy of a processed document.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $isSecretary = in_array($role, [ROLE_SECRETARY_DEPUTY, ROLE_SECRETARY_CO], true);
    $targetId = (int)($_POST['doc_id'] ?? 0);
    if ($isSecretary && $targetId === $id) {
        try {
            if (isset($_POST['save_copy'])) {
                save_document_copy($id, (int) $user['id'], $role);
                set_flash('success', 'Copy of ' . $doc['tracking_number'] . ' has been saved.');
            } elseif (isset($_POST['remove_copy'])) {
                remove_document_copy($id, (int) $user['id']);
                set_flash('success', 'Copy of ' . $doc['tracking_number'] . ' has been removed.');
            }
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . '/view_document.php?id=' . $id);
    exit;
}

$hasCopy = has_saved_copy($id, (int) $user['id']);
$isSecretary = in_array($role, [ROLE_SECRETARY_DEPUTY, ROLE_SECRETARY_CO], true);

// Privilege check: Branch users may only view docs they created or that relate to their branch.
$secDir = ($role === ROLE_SECRETARY_CO) ? 'secretary_co' : 'secretary_deputy';

page_header('Document Detail: ' . $doc['tracking_number'], 'documents', $user);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL . '/' . ($role === ROLE_ADMIN ? 'admin' : strtolower($role)) . '/dashboard.php' ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Document Details</li>
            </ol>
        </nav>
        <h4 class="mb-0"><?= e($doc['subject']) ?></h4>
        <div class="text-muted small"><?= e($doc['tracking_number']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isSecretary): ?>
            <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="doc_id" value="<?= $id ?>">
                <?php if ($hasCopy): ?>
                    <button type="submit" name="remove_copy" value="1" class="btn btn-outline-danger btn-sm"><i class="bi bi-clipboard-minus me-1"></i>Remove Copy</button>
                <?php else: ?>
                    <button type="submit" name="save_copy" value="1" class="btn btn-outline-primary btn-sm"><i class="bi bi-clipboard-plus me-1"></i>Save Copy</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<?php
// Permission / ownership banner: informs the user whether they hold the
// document (can act) or are only able to monitor it (view-only).
$permMode = match ($permission) {
    'active'          => 'success',
    'awaiting_receipt' => 'warning',
    default           => 'secondary',
};
$permTitle = match ($permission) {
    'active'           => 'ACTIVE HOLDER',
    'awaiting_receipt' => 'AWAITING YOUR RECEIPT',
    default            => 'VIEW ONLY',
};
$permMsg = match ($permission) {
    'active'           => 'You currently hold this document and may process, forward, or update it.',
    'awaiting_receipt' => 'This document has been forwarded to you. Please confirm receipt before taking other actions.',
    default            => 'This document is currently with ' . e($doc['holder_name'] ?? 'another user') . '. You can monitor it, but you cannot modify routing or status.',
};
?>
<div class="alert alert-<?= $permMode ?> py-2 small d-flex align-items-center mb-3">
    <i class="bi <?= $permission === 'view_only' ? 'bi-eye' : 'bi-check2-circle' ?> me-2"></i>
    <strong class="me-2"><?= $permTitle ?></strong> — <?= $permMsg ?>
</div>

<div class="row g-3">
    <!-- Document info -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-file-earmark-text me-2"></i>Document Information</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-sm-5 text-muted">Document Type</dt>
                    <dd class="col-sm-7"><?= e($doc['document_type'] ?? '—') ?></dd>
                    <dt class="col-sm-5 text-muted">Originating Branch</dt>
                    <dd class="col-sm-7"><?= e($doc['origin_branch_name'] ?? '—') ?></dd>
                    <dt class="col-sm-5 text-muted">Sender</dt>
                    <dd class="col-sm-7"><?= e($doc['sender'] ?? '—') ?></dd>
                    <dt class="col-sm-5 text-muted">Date Logged</dt>
                    <dd class="col-sm-7"><?= e(date('F j, Y g:i A', strtotime($doc['created_at']))) ?></dd>
                    <dt class="col-sm-5 text-muted">Date Received</dt>
                    <dd class="col-sm-7"><?= e($doc['date_received'] ? date('F j, Y', strtotime($doc['date_received'])) : '—') ?></dd>
                    <dt class="col-sm-5 text-muted">Priority</dt>
                    <dd class="col-sm-7"><?= priority_badge($doc['priority']) ?></dd>
                    <dt class="col-sm-5 text-muted">Status</dt>
                    <dd class="col-sm-7"><?= status_badge($doc['current_status']) ?></dd>
                    <?php $age = doc_age_days($doc); ?>
                    <dt class="col-sm-5 text-muted">Age</dt>
                    <dd class="col-sm-7"><?= aging_badge($age) ?></dd>
                    <dt class="col-sm-5 text-muted">Remarks</dt>
                    <dd class="col-sm-7"><?= nl2br(e($doc['remarks'] ?? '—')) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Current location -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-geo-alt me-2"></i>Current Location</div>
            <div class="card-body small">
                <div class="mb-2"><span class="text-muted">Current Holder:</span> <strong><?= e($doc['holder_name'] ?? 'Unassigned') ?></strong></div>
                <div class="mb-2"><span class="text-muted">Role:</span> <?= e(role_label($doc['holder_role'] ?? '')) ?></div>
                <div class="mb-2"><span class="text-muted">Office/Branch:</span> <?= e($doc['current_branch_name'] ?? '—') ?></div>
                <div class="mb-2"><span class="text-muted">Position:</span> <?= e($doc['holder_position'] ?? '—') ?></div>
                <div><span class="text-muted">Last Updated:</span> <?= e(date('F j, Y g:i A', strtotime($doc['updated_at']))) ?></div>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-clock-history me-2"></i>Routing History</div>
            <div class="card-body">
                <?php if (!$events): ?>
                    <div class="text-muted small">No tracking history available.</div>
                <?php else: ?>
                    <ul class="timeline">
                        <?php foreach ($events as $ev):
                            $fmt = date('M d, Y g:i A', strtotime($ev['created_at']));
                            $action = $ev['action'];
                            $icon = match ($action) {
                                'CREATED'   => 'bi-plus-circle',
                                'RECEIVED'  => 'bi-inbox',
                                'FORWARDED' => 'bi-send',
                                'RETURNED_TO_SENDER' => 'bi-arrow-return-left',
                                'STATUS_CHANGED' => 'bi-pencil-square',
                                'SIGNED'    => 'bi-pen',
                                'APPROVED'  => 'bi-check-circle',
                                default     => 'bi-dot',
                            };
                            $fromName = ($ev['from_user_id'] ? (fetch_user((int)$ev['from_user_id'])['full_name'] ?? 'User') : '—');
                            $toName   = ($ev['to_user_id'] ? (fetch_user((int)$ev['to_user_id'])['full_name'] ?? 'User') : '—');
                        ?>
                        <li class="timeline-item">
                            <div class="timeline-icon bg-primary text-white"><i class="bi <?= $icon ?>"></i></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-uppercase small text-primary"><?= e($action) ?></strong>
                                    <span class="text-muted small"><?= e($fmt) ?></span>
                                </div>
                                <div class="small mt-1">
                                    <?php if ($ev['from_user_id']): ?><span class="me-2"><i class="bi bi-person"></i> From: <?= e($fromName) ?></span><?php endif; ?>
                                    <?php if ($ev['to_user_id']): ?><span><i class="bi bi-send"></i> To: <?= e($toName) ?></span><?php endif; ?>
                                    <?php if ($ev['status']): ?><div class="mt-1">Status: <?= status_badge($ev['status']) ?></div><?php endif; ?>
                                </div>
                                <?php if ($ev['remarks']): ?>
                                    <div class="text-muted small mt-1 border-start ps-2 border-3"><?= nl2br(e($ev['remarks'])) ?></div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Processing history (permanent per-holder snapshots) -->
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-light fw-semibold"><i class="bi bi-clipboard-check me-2"></i>Processing History</div>
            <div class="card-body">
                <?php if (!$processing): ?>
                    <div class="text-muted small">No processing records yet.</div>
                <?php else: ?>
                    <ol class="list-group list-group-numbered">
                        <?php foreach ($processing as $pr): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                    <div>
                                        <strong><?= e($pr['user_name'] ?: 'User #' . (int)$pr['user_id']) ?></strong>
                                        <span class="badge bg-secondary ms-1"><?= e(role_label((string)$pr['user_role'] ?? '')) ?></span>
                                        <?php if ($pr['user_position']): ?><span class="text-muted small ms-1">— <?= e($pr['user_position']) ?></span><?php endif; ?>
                                    </div>
                                    <div class="small text-uppercase">
                                        <span class="badge bg-primary"><?= e($pr['action']) ?></span>
                                        <?php if ($pr['status']): ?><?= status_badge($pr['status']) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="row small mt-2 text-muted">
                                    <div class="col-6 col-md-3">
                                        <i class="bi bi-box-arrow-in-right me-1"></i>Received:
                                        <?= $pr['received_at'] ? e(date('M d, Y g:i A', strtotime($pr['received_at']))) : '—' ?>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <i class="bi bi-check2-circle me-1"></i>Processed:
                                        <?= $pr['processed_at'] ? e(date('M d, Y g:i A', strtotime($pr['processed_at']))) : '—' ?>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <i class="bi bi-send me-1"></i>Forwarded To:
                                        <?php if ($pr['to_user_id']):
                                            $prTo = fetch_user((int)$pr['to_user_id']);
                                            echo e($prTo['full_name'] ?? 'User #' . (int)$pr['to_user_id']);
                                        else: ?>—<?php endif; ?>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        <?= e($pr['to_branch_name'] ?? '—') ?>
                                    </div>
                                </div>
                                <?php if ($pr['remarks']): ?>
                                    <div class="text-muted small mt-1 border-start ps-2 border-3"><?= nl2br(e($pr['remarks'])) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
page_footer();
