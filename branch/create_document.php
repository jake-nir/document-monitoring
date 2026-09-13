<?php
/**
 * Branch: Log a new document (metadata only - no file upload).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_BRANCH);
$uid = (int)$user['id'];
$branchId = (int)$user['branch_id'];

$errors = [];
$old = $_POST;

// Build eligible routing targets for this role (for initial destination selection)
$targetRoles = route_targets(ROLE_BRANCH);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $subject = trim((string)($_POST['subject'] ?? ''));
    $tracking = trim((string)($_POST['tracking_number'] ?? ''));
    $docType = trim((string)($_POST['document_type'] ?? ''));
    $sender = trim((string)($_POST['sender'] ?? ''));
    $priority = $_POST['priority'] ?? 'Normal';
    $dateReceived = $_POST['date_received'] ?? date('Y-m-d');
    $remarks = trim((string)($_POST['remarks'] ?? ''));
    $initialDest = (int)($_POST['initial_destination'] ?? 0);

    if ($tracking === '') { $errors[] = 'Document Control / Tracking Number is required.'; }
    elseif (mb_strlen($tracking) > 50) { $errors[] = 'Document number must be 50 characters or fewer.'; }
    if ($subject === '') { $errors[] = 'Document title/subject is required.'; }
    if (!in_array($priority, ['Low','Normal','High','Urgent'], true)) { $priority = 'Normal'; }

    if (!$errors) {
        $check = db()->prepare('SELECT COUNT(*) FROM documents WHERE tracking_number = ? OR doc_control_number = ?');
        $check->execute([$tracking, $tracking]);
        if ((int)$check->fetchColumn() > 0) {
            $errors[] = 'The tracking number "' . $tracking . '" already exists. Please use a different number.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $holderId = $initialDest > 0 ? $initialDest : $uid;
            $holderBranch = $initialDest > 0 ? (fetch_user($initialDest)['branch_id'] ?? $branchId) : $branchId;

            $stmt = $pdo->prepare(
                'INSERT INTO documents (tracking_number, doc_control_number, subject, document_type, sender, originating_branch_id, created_by, current_holder_id, current_branch_id, current_status, priority, date_received, remarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$tracking, null, $subject, $docType ?: null, $sender ?: null, $branchId, $uid, $holderId, $holderBranch, 'NEW', $priority, $dateReceived ?: null, $remarks ?: null]);
            $docId = (int)$pdo->lastInsertId();

            insert_tracking($docId, $uid, $branchId, $holderId, $holderBranch, ACTION_CREATED, 'NEW', $remarks ?: 'Document created.');

            // Open the creator's permanent processing record.
            create_processing_record($docId, $uid, $branchId, $user['role'], ACTION_CREATED, 'NEW', null, null, null, null, $remarks ?: 'Document created.');

            // If an initial destination was chosen, forward immediately in same transaction.
            if ($initialDest > 0 && $initialDest !== $uid) {
                $toUser = fetch_user($initialDest);
                if ($toUser && $toUser['status'] === 'active' && $toUser['branch_id']) {
                    // routing check
                    if (!can_route(ROLE_BRANCH, $toUser['role'])) {
                        throw new RuntimeException('Unauthorized routing destination.');
                    }
                    insert_tracking($docId, $uid, $branchId, $initialDest, $toUser['branch_id'], ACTION_FORWARDED, 'NEW', 'Forwarded to ' . $toUser['full_name']);
                    $pdo->prepare('UPDATE documents SET current_holder_id = ?, current_branch_id = ?, updated_at=NOW() WHERE id = ?')->execute([$initialDest, $toUser['branch_id'], $docId]);
                    complete_processing_record($docId, $uid, ACTION_FORWARDED, 'NEW', $initialDest, $toUser['branch_id'], 'Forwarded to ' . $toUser['full_name']);
                    notify((int)$initialDest, $docId, 'You have received a new document: ' . $tracking . '.');
                    if (in_array($toUser['role'], [ROLE_SECRETARY_DEPUTY, ROLE_SECRETARY_CO], true)) {
                        $pdo->prepare('INSERT INTO document_copies (user_id, document_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id')->execute([$initialDest, $docId]);
                    }
                }
            }

            audit('DOCUMENT_CREATED', 'documents', $docId, 'Tracking: ' . $tracking, $uid);
            $pdo->commit();

            set_flash('success', "Document {$tracking} logged successfully.");
            header('Location: ' . BASE_URL . '/view_document.php?id=' . $docId);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            log_error('create_document failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

// Available destination users (routing-eligible)
$destUsers = [];
foreach ($targetRoles as $r) {
    $stmt = db()->prepare('SELECT u.id, u.full_name, u.position, u.role, b.branch_name FROM users u LEFT JOIN branches b ON b.id=u.branch_id WHERE u.role=? AND u.status="active" AND u.branch_id IS NOT NULL ORDER BY u.full_name');
    $stmt->execute([$r]);
    foreach ($stmt->fetchAll() as $u) { $destUsers[] = $u; }
}

page_header('Log Document', 'create', $user);
?>
<h4 class="mb-3"><i class="bi bi-file-earmark-plus me-2"></i>Log a New Document</h4>

<?php if ($errors): ?>
    <div class="alert alert-danger small"><?= implode('<br>', array_map('e', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="card shadow-sm border-0">
    <div class="card-body">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Document Control / Tracking Number <span class="text-danger">*</span></label>
                <input type="text" name="tracking_number" class="form-control" value="<?= e($old['tracking_number'] ?? '') ?>" placeholder="e.g. SR-2026-0001" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Document Title / Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject" class="form-control" value="<?= e($old['subject'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Document Type</label>
                <select name="document_type" class="form-select">
                    <option value="">-- Select --</option>
                    <?php foreach (['Request','Memo','Report','Letter','Resolution','Contract','Other'] as $t): ?>
                        <option value="<?= e($t) ?>" <?= ($old['document_type'] ?? '')===$t?'selected':'' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Sender</label>
                <input type="text" name="sender" class="form-control" value="<?= e($old['sender'] ?? '') ?>" placeholder="Name of sender / office">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Originating Branch</label>
                <input type="text" class="form-control" value="<?= e(fetch_branch($branchId)['branch_name'] ?? '') ?>" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Date Received</label>
                <input type="date" name="date_received" class="form-control" value="<?= e($old['date_received'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Priority</label>
                <select name="priority" class="form-select">
                    <?php foreach (['Low','Normal','High','Urgent'] as $p): ?>
                        <option value="<?= e($p) ?>" <?= ($old['priority'] ?? 'Normal')===$p?'selected':'' ?>><?= e($p) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Initial Destination (optional)</label>
                <select name="initial_destination" class="form-select">
                    <option value="0">-- Keep with me --</option>
                    <?php foreach ($destUsers as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= ($old['initial_destination'] ?? '')==$d['id']?'selected':'' ?>>
                            <?= e($d['full_name']) ?> (<?= e(role_label($d['role'])) ?> - <?= e($d['branch_name'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold small">Initial Remarks</label>
                <textarea name="remarks" class="form-control" rows="3"><?= e($old['remarks'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="card-footer bg-light d-flex justify-content-end gap-2">
        <a href="<?= BASE_URL ?>/branch/documents.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
        <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Log Document</button>
    </div>
</form>
<?php page_footer(); ?>
