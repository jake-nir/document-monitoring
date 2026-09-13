<?php
/**
 * Core business logic: routing, tracking, aging, and document operations.
 */

declare(strict_types=1);

require_once CONFIG_PATH . DS . 'database.php';

// ---------------------------------------------------------------------
// Status helpers
// ---------------------------------------------------------------------
function status_label(string $status): string
{
    return match (strtoupper($status)) {
        'PENDING'  => 'PENDING',
        'SIGNED'   => 'SIGNED',
        'APPROVED' => 'APPROVED',
        'RTS'      => 'RTS',
        'RECEIVED' => 'RECEIVED',
        'NEW'      => 'NEW',
        default    => strtoupper($status),
    };
}

function status_badge(string $status): string
{
    $s = strtoupper($status);
    $class = match ($s) {
        'PENDING'  => 'bg-warning text-dark',
        'SIGNED'   => 'bg-primary',
        'APPROVED' => 'bg-success',
        'RTS'      => 'bg-danger',
        'RECEIVED' => 'bg-info text-dark',
        'NEW'      => 'bg-secondary',
        default    => 'bg-secondary',
    };
    return '<span class="badge ' . $class . '">' . e($s) . '</span>';
}

function priority_badge(string $priority): string
{
    $p = strtolower($priority);
    $class = match ($p) {
        'urgent' => 'bg-danger',
        'high'   => 'bg-warning text-dark',
        'normal' => 'bg-secondary',
        default  => 'bg-secondary',
    };
    return '<span class="badge ' . $class . '">' . e(ucfirst($priority)) . '</span>';
}

// ---------------------------------------------------------------------
// Role display
// ---------------------------------------------------------------------
function role_label(string $role): string
{
    return match ($role) {
        'BRANCH'           => 'Branch',
        'SECRETARY_DEPUTY' => 'Secretary Deputy',
        'SECRETARY_CO'     => 'Secretary of CO',
        'ADMIN'            => 'Administrator',
        default            => $role,
    };
}

// ---------------------------------------------------------------------
// Document aging (days in current location)
// ---------------------------------------------------------------------
function doc_age_days(array $doc): int
{
    // Use the latest received/forward timestamp at the current holder, else created_at.
    $base = $doc['updated_at'] ?? $doc['created_at'] ?? null;
    if (!$base) {
        return 0;
    }
    $ts = strtotime($base);
    if (!$ts) {
        return 0;
    }
    return max(0, (int)floor((time() - $ts) / 86400));
}

function aging_level(int $days): string
{
    $normal = max(1, (int)setting('aging_normal', '2'));
    $attention = max($normal + 1, (int)setting('aging_attention', '4'));
    if ($days > $attention) {
        return 'overdue';
    }
    if ($days > $normal) {
        return 'attention';
    }
    return 'normal';
}

function aging_badge(int $days): string
{
    $level = aging_level($days);
    $class = match ($level) {
        'normal'    => 'bg-success',
        'attention' => 'bg-warning text-dark',
        'overdue'   => 'bg-danger',
        default     => 'bg-secondary',
    };
    $label = match ($level) {
        'normal'    => 'Normal',
        'attention' => 'Attention',
        'overdue'   => 'Overdue',
        default     => '',
    };
    return '<span class="badge ' . $class . '" title="Document age: ' . $days . ' day(s)">' . e($label) . ' (' . $days . 'd)</span>';
}

/**
 * Redirect based on the user's role.
 */
function redirect_by_role(string $role): void
{
    $url = match ($role) {
        ROLE_BRANCH           => BASE_URL . '/branch/dashboard.php',
        ROLE_SECRETARY_DEPUTY => BASE_URL . '/secretary_deputy/dashboard.php',
        ROLE_SECRETARY_CO     => BASE_URL . '/secretary_co/dashboard.php',
        ROLE_ADMIN            => BASE_URL . '/admin/dashboard.php',
        default               => BASE_URL . '/login.php',
    };
    header('Location: ' . $url);
    exit;
}

// ---------------------------------------------------------------------
// Tracking helpers
// ---------------------------------------------------------------------

/**
 * Insert an immutable tracking record.
 */
function insert_tracking(int $docId, ?int $fromUserId, ?int $fromBranchId, ?int $toUserId, ?int $toBranchId, string $action, ?string $status, ?string $remarks): int
{
    db()->prepare(
        'INSERT INTO document_tracking
           (document_id, from_user_id, from_branch_id, to_user_id, to_branch_id, action, status, remarks)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$docId, $fromUserId, $fromBranchId, $toUserId, $toBranchId, $action, $status, $remarks]);
    return (int)db()->lastInsertId();
}

/**
 * Status badge map for the timeline rendering.
 */

/**
 * Returns the latest tracking event for a document, or null.
 */
function latest_tracking(int $docId): ?array
{
    $stmt = db()->prepare('SELECT * FROM document_tracking WHERE document_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * True if the latest event means $userId must confirm receipt:
 * either a FORWARDED sent TO them, or a RETURNED_TO_SENDER (RTS) sent back to them.
 */
function awaiting_receipt(int $docId, int $userId): bool
{
    $last = latest_tracking($docId);
    return $last !== null
        && in_array($last['action'], [ACTION_FORWARDED, ACTION_RTS], true)
        && $last['to_user_id'] !== null
        && (int)$last['to_user_id'] === $userId;
}

/**
 * Documents awaiting receipt by $userId:
 * either forwarded to them or returned (RTS) back to them.
 */
function incoming_documents(int $userId): array
{
    $sql = 'SELECT d.*, b.branch_name AS origin_branch, t.created_at AS received_at
            FROM documents d
            INNER JOIN document_tracking t ON t.id = (
                SELECT MAX(t2.id) FROM document_tracking t2 WHERE t2.document_id = d.id
            )
            LEFT JOIN branches b ON b.id = d.originating_branch_id
            WHERE t.action IN (?, ?) AND t.to_user_id = ?
            ORDER BY t.created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute([ACTION_FORWARDED, ACTION_RTS, $userId]);
    return $stmt->fetchAll();
}

/**
 * Documents currently held by $userId but NOT awaiting receipt
 * (i.e. already received / created by them, still with them).
 */
function my_documents(int $userId): array
{
    $sql = 'SELECT d.*, b.branch_name AS origin_branch, t.created_at AS last_event_at
            FROM documents d
            INNER JOIN document_tracking t ON t.id = (
                SELECT MAX(t2.id) FROM document_tracking t2 WHERE t2.document_id = d.id
            )
            LEFT JOIN branches b ON b.id = d.originating_branch_id
            WHERE d.current_holder_id = ? AND t.action <> ?
            ORDER BY t.created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId, ACTION_FORWARDED]);
    return $stmt->fetchAll();
}

function incoming_count(int $userId): int
{
    return count(incoming_documents($userId));
}

// ---------------------------------------------------------------------
// CURRENT-HOLDER AUTHORIZATION (central ownership enforcement)
// ---------------------------------------------------------------------

/**
 * True ONLY if $userId is the document's CURRENT holder.
 *
 * Being the creator, originator, or a previous holder grants NO processing
 * rights. The current holder is authoritative for every mutating action.
 */
function can_process_document(int $userId, int $docId): bool
{
    if ($userId <= 0 || $docId <= 0) {
        return false;
    }
    $stmt = db()->prepare('SELECT current_holder_id FROM documents WHERE id = ? LIMIT 1');
    $stmt->execute([$docId]);
    $holderId = $stmt->fetchColumn();
    return $holderId !== false && (int)$holderId === $userId;
}

/**
 * Throw a clear, user-facing error unless $userId is the document's current holder.
 * Must be re-checked server-side inside every mutating workflow.
 */
function assert_current_holder(array $doc, int $userId): void
{
    if ((int)($doc['current_holder_id'] ?? 0) !== $userId) {
        throw new RuntimeException(
            'You cannot perform this action because this document is no longer assigned to you.'
        );
    }
}

/**
 * The current holder is authoritative for every mutating action,
 * regardless of who created / previously held / originated the document.
 */
function is_current_holder(array $doc, int $userId): bool
{
    return (int)($doc['current_holder_id'] ?? 0) === $userId;
}

/**
 * User's relationship to a document, used by the UI to show the correct actions:
 *  - 'active'           => current holder, may process / forward / act.
 *  - 'awaiting_receipt' => current holder but must confirm receipt first.
 *  - 'view_only'        => historical participant; may only monitor.
 */
function user_document_permission(array $doc, int $userId): string
{
    if (!is_current_holder($doc, $userId)) {
        return 'view_only';
    }
    return awaiting_receipt((int)$doc['id'], $userId) ? 'awaiting_receipt' : 'active';
}

// ---------------------------------------------------------------------
// PROCESSING RECORDS (permanent per-holder exercise snapshot)
// ---------------------------------------------------------------------

/**
 * Open a new processing record for a holder stage.
 * One record is created when a user receives the document (or creates it);
 * it is completed (processed_at / destination / final remarks) the moment the
 * user forwards the document, returns it (RTS), or changes its status.
 * Records are immutable once the user loses possession.
 */
function create_processing_record(int $docId, int $userId, int $branchId, string $role, string $action, ?string $status, ?int $fromUserId, ?int $fromBranchId, ?int $toUserId, ?int $toBranchId, ?string $remarks, ?string $receivedAt = null): int
{
    $stmt = db()->prepare(
        'INSERT INTO document_processing_records
           (document_id, user_id, branch_id, role, action, status, received_at,
            from_user_id, from_branch_id, to_user_id, to_branch_id, remarks)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $docId, $userId, $branchId, $role, $action, $status, $receivedAt,
        $fromUserId, $fromBranchId, $toUserId, $toBranchId, $remarks,
    ]);
    return (int)db()->lastInsertId();
}

/**
 * Complete / update the latest open processing record for $userId on $docId.
 * Preserves the original processed_at (first processing timestamp) while
 * recording the final status, action, forwarding destination and remarks.
 */
function complete_processing_record(int $docId, int $userId, string $action, ?string $status, ?int $toUserId, ?int $toBranchId, ?string $remarks): void
{
    db()->prepare(
        'UPDATE document_processing_records SET
            processed_at   = COALESCE(processed_at, NOW()),
            status         = ?,
            action         = ?,
            to_user_id     = ?,
            to_branch_id   = ?,
            remarks        = ?
         WHERE document_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1'
    )->execute([$status, $action, $toUserId, $toBranchId, $remarks, $docId, $userId]);
}

/**
 * Full processing history of a document (permanent, ordered snapshot).
 */
function processing_records(int $docId): array
{
    $sql = 'SELECT pr.*,
                   u.full_name   AS user_name,
                   u.role        AS user_role,
                   u.position    AS user_position,
                   fb.branch_name AS from_branch_name,
                   tb.branch_name AS to_branch_name
            FROM document_processing_records pr
            LEFT JOIN users u     ON u.id  = pr.user_id
            LEFT JOIN branches fb ON fb.id = pr.from_branch_id
            LEFT JOIN branches tb ON tb.id = pr.to_branch_id
            WHERE pr.document_id = ?
            ORDER BY pr.id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute([$docId]);
    return $stmt->fetchAll();
}

/**
 * The stage a given user is in for this document ('open' = still being
 * processed; 'completed' = user no longer holds it).
 */
function user_processing_stage(int $docId, int $userId): ?string
{
    $stmt = db()->prepare(
        'SELECT action, processed_at FROM document_processing_records
         WHERE document_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$docId, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return ($row['processed_at'] === null) ? 'open' : 'completed';
}

// ---------------------------------------------------------------------
// PERSONAL DOCUMENT COPIES (secretaries - copies of processed docs)
// ---------------------------------------------------------------------

function has_saved_copy(int $docId, int $userId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM document_copies WHERE document_id = ? AND user_id = ?');
    $stmt->execute([$docId, $userId]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Save a personal copy. Only secretary roles may do this, and only for
 * documents the user has processed (authored at least one tracking event).
 *
 * @throws RuntimeException when the user is not allowed to save a copy.
 */
function save_document_copy(int $docId, int $userId, string $role): bool
{
    if (!in_array($role, [ROLE_SECRETARY_DEPUTY, ROLE_SECRETARY_CO], true)) {
        throw new RuntimeException('Only secretaries may save document copies.');
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM document_tracking WHERE document_id = ? AND from_user_id = ?');
    $stmt->execute([$docId, $userId]);
    if ((int) $stmt->fetchColumn() === 0) {
        throw new RuntimeException('You can only save copies of documents you have processed.');
    }
    $pdo->prepare('INSERT INTO document_copies (user_id, document_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id')
        ->execute([$userId, $docId]);
    audit('DOCUMENT_COPY_SAVED', 'documents', $docId, 'Copy saved by user ' . $userId, $userId);
    return true;
}

function remove_document_copy(int $docId, int $userId): bool
{
    db()->prepare('DELETE FROM document_copies WHERE user_id = ? AND document_id = ?')
        ->execute([$userId, $docId]);
    audit('DOCUMENT_COPY_REMOVED', 'documents', $docId, 'Copy removed by user ' . $userId, $userId);
    return true;
}

function saved_copies(int $userId, string $q = ''): array
{
    $sql = 'SELECT c.id AS copy_id, c.created_at AS saved_at,
                   d.*, b.branch_name AS origin_branch
            FROM document_copies c
            INNER JOIN documents d ON d.id = c.document_id
            LEFT JOIN branches b ON b.id = d.originating_branch_id
            WHERE c.user_id = ?';
    $params = [$userId];
    if ($q !== '') {
        $sql .= ' AND (d.tracking_number LIKE ? OR d.subject LIKE ? OR d.doc_control_number LIKE ? OR d.document_type LIKE ? OR d.sender LIKE ?)';
        $like = "%$q%";
        array_push($params, $like, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY c.created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ---------------------------------------------------------------------
// ATOMIC DOCUMENT OPERATIONS (wrapped in transactions)
// ---------------------------------------------------------------------

/** 
 * Create a new document. Returns the new document ID.
 * Tracking/control number is a single user-supplied value and must be unique.
 * Within a transaction: duplicate check, insert document, create CREATED tracking,
 * set current holder, create audit log.
 *
 * @throws RuntimeException when the tracking number already exists.
 */
function create_document(array $data, int $creatorId): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $tracking = trim((string)($data['tracking_number'] ?? ''));
        if ($tracking === '') {
            throw new RuntimeException('Document Control / Tracking Number is required.');
        }
        $dup = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE tracking_number = ? OR doc_control_number = ?');
        $dup->execute([$tracking, $tracking]);
        if ((int)$dup->fetchColumn() > 0) {
            throw new RuntimeException('The tracking number "' . $tracking . '" already exists. Please use a different number.');
        }
        $branchId = $data['originating_branch_id'];
        $holderId = $data['current_holder_id'] ?? $creatorId;
        $holderBranch = $data['current_branch_id'] ?? $branchId;

        $stmt = $pdo->prepare(
            'INSERT INTO documents
               (tracking_number, doc_control_number, subject, document_type, sender,
                originating_branch_id, created_by, current_holder_id, current_branch_id,
                current_status, priority, date_received, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tracking,
            null,
            trim($data['subject']),
            $data['document_type'] ?? null,
            $data['sender'] ?? null,
            $branchId,
            $creatorId,
            $holderId,
            $holderBranch,
            $data['current_status'] ?? 'NEW',
            $data['priority'] ?? 'Normal',
            $data['date_received'] ?? date('Y-m-d'),
            $data['remarks'] ?? null,
        ]);
        $docId = (int)$pdo->lastInsertId();

        insert_tracking($docId, $creatorId, $branchId, $holderId, $holderBranch, ACTION_CREATED, 'NEW', $data['remarks'] ?? 'Document created.');

        // Open the creator's permanent processing record (stage 1).
        $creator = fetch_user($creatorId);
        create_processing_record($docId, $creatorId, (int)$branchId, $creator['role'] ?? '', ACTION_CREATED, 'NEW', null, null, null, null, $data['remarks'] ?? 'Document created.');

        audit('DOCUMENT_CREATED', 'documents', $docId, 'Tracking: ' . $tracking, $creatorId);

        $pdo->commit();
        return $docId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_error('create_document failed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Forward a document to a destination user.
 * Atomic: tracking + holder update + notification + audit.
 */
function forward_document(int $docId, int $fromUserId, int $toUserId, ?string $remarks): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $doc = fetch_document($docId);
        if (!$doc) {
            throw new RuntimeException('Document not found.');
        }

        // CORE OWNERSHIP RULE: only the CURRENT holder may forward.
        assert_current_holder($doc, $fromUserId);
        if (awaiting_receipt($docId, $fromUserId)) {
            throw new RuntimeException('You must receive the document first before you can forward it.');
        }

        $to = fetch_user($toUserId);
        if (!$to || $to['status'] !== 'active') {
            throw new RuntimeException('Invalid destination user.');
        }
        if (!$to['branch_id']) {
            throw new RuntimeException('Destination user has no assigned branch/office.');
        }

        $fromBranchId = $doc['current_branch_id'] ?? $doc['originating_branch_id'];

        // Insert tracking
        insert_tracking($docId, $fromUserId, $fromBranchId, $toUserId, $to['branch_id'], ACTION_FORWARDED, $doc['current_status'], $remarks);

        // Update current holder (keeps status unchanged until receiver acts)
        $pdo->prepare('UPDATE documents SET current_holder_id = ?, current_branch_id = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$toUserId, $to['branch_id'], $docId]);

        // Notify the receiving user
        notify($toUserId, $docId, 'You have received a new document: ' . $doc['tracking_number'] . '.');

        // Complete the sender's permanent processing record.
        complete_processing_record($docId, $fromUserId, ACTION_FORWARDED, $doc['current_status'], $toUserId, $to['branch_id'], $remarks);

        // Automatically save a copy for secretary recipients (Deputy & CO).
        if (in_array($to['role'], [ROLE_SECRETARY_DEPUTY, ROLE_SECRETARY_CO], true)) {
            $pdo->prepare('INSERT INTO document_copies (user_id, document_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id')
                ->execute([$toUserId, $docId]);
        }

        audit('DOCUMENT_FORWARDED', 'documents', $docId, 'From user ' . $fromUserId . ' to user ' . $toUserId, $fromUserId);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_error('forward_document failed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Receive a document (confirm arrival at current holder).
 */
function receive_document(int $docId, int $userId): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $doc = fetch_document($docId);
        if (!$doc) {
            throw new RuntimeException('Document not found.');
        }
        // Only the current holder may receive.
        if ((int)$doc['current_holder_id'] !== $userId) {
            throw new RuntimeException('You are not the current holder of this document.');
        }
        // Only allow receiving documents that are actually awaiting receipt.
        if (!awaiting_receipt($docId, $userId)) {
            throw new RuntimeException('This document is not waiting to be received.');
        }

        // Set status to RECEIVED (only if still NEW) to record the receipt moment.
        $newStatus = in_array($doc['current_status'], ['NEW', 'RTS'], true) ? 'RECEIVED' : $doc['current_status'];

        insert_tracking($docId, $userId, $doc['current_branch_id'], null, null, ACTION_RECEIVED, $newStatus, 'Document received.');

        // Open the receiver's permanent processing record.
        $receiver = fetch_user($userId);
        $last = latest_tracking($docId);
        create_processing_record(
            $docId,
            $userId,
            (int)($doc['current_branch_id'] ?? 0),
            $receiver['role'] ?? '',
            ACTION_RECEIVED,
            $newStatus,
            $last ? (int)$last['from_user_id'] : null,
            $last ? (int)$last['from_branch_id'] : null,
            null,
            null,
            'Document received.',
            date('Y-m-d H:i:s')
        );

        if ($newStatus !== $doc['current_status']) {
            log_status_change($docId, $doc['current_status'], $newStatus, $userId, 'Document received.');
            $pdo->prepare('UPDATE documents SET current_status = ?, updated_at = NOW() WHERE id = ?')->execute([$newStatus, $docId]);
        }

        audit('DOCUMENT_RECEIVED', 'documents', $docId, 'Received by user ' . $userId, $userId);

        // The "received a new document" notification is fulfilled now.
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND document_id = ? AND is_read = 0')
            ->execute([$userId, $docId]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_error('receive_document failed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Change a document's status (PENDING/SIGNED/APPROVED/RTS).
 * RTS requires remarks and returns the document to the previous sender.
 * Atomic operation.
 */
function change_status(int $docId, int $userId, string $newStatus, ?string $remarks = null): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $doc = fetch_document($docId);
        if (!$doc) {
            throw new RuntimeException('Document not found.');
        }
        // CORE OWNERSHIP RULE: only the CURRENT holder may change status.
        assert_current_holder($doc, $userId);
        $newStatus = strtoupper($newStatus);
        if (!in_array($newStatus, DOC_STATUSES, true)) {
            throw new RuntimeException('Invalid status.');
        }
        if ($newStatus === $doc['current_status']) {
            $pdo->commit();
            return true;
        }

        $oldStatus = $doc['current_status'];

        if ($newStatus === STATUS_RTS) {
            if (trim((string)$remarks) === '') {
                throw new RuntimeException('Remarks/reason is required for RTS.');
            }
            $rtsOk = do_rts($doc, $userId, $remarks);
            $pdo->commit();
            return $rtsOk;
        }

        // Normal status update (PENDING / SIGNED / APPROVED)
        $action = match ($newStatus) {
            'SIGNED'   => ACTION_SIGNED,
            'APPROVED' => ACTION_APPROVED,
            default    => ACTION_STATUS,
        };

        insert_tracking($docId, $userId, $doc['current_branch_id'], null, null, $action, $newStatus, $remarks ?: null);
        log_status_change($docId, $oldStatus, $newStatus, $userId, $remarks);
        $pdo->prepare('UPDATE documents SET current_status = ?, updated_at = NOW() WHERE id = ?')->execute([$newStatus, $docId]);

        // Record this processing activity on the holder's permanent record.
        complete_processing_record($docId, $userId, $action, $newStatus, null, null, $remarks ?: 'Status changed to ' . $newStatus);

        // Notify intended subsequent locations is not required here; just audit.
        audit('DOCUMENT_STATUS_CHANGED', 'documents', $docId, 'Status ' . $oldStatus . ' -> ' . $newStatus, $userId);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_error('change_status failed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * RTS - Return To Sender.
 * Determines the previous sender from tracking history and returns the
 * document to them, preserving all history.
 */
function do_rts(array $doc, int $userId, string $remarks): bool
{
    $pdo = db();

    // Find the previous sender: the FROM of the last FORWARDED-to-me event
    // (i.e. the person who forwarded the document into the current holder's hands).
    $stmt = $pdo->prepare(
        'SELECT from_user_id, from_branch_id FROM document_tracking
         WHERE document_id = ? AND action = ? AND to_user_id = ?
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$doc['id'], ACTION_FORWARDED, $userId]);
    $prev = $stmt->fetch();

    $returnToUserId = $prev['from_user_id'] ?? $doc['created_by'];
    $returnToBranchId = $prev['from_branch_id'] ?? $doc['originating_branch_id'];

    $retUser = fetch_user((int)$returnToUserId);
    if ($retUser) {
        $returnToBranchId = $retUser['branch_id'] ?? $returnToBranchId;
    }

    insert_tracking($doc['id'], $userId, $doc['current_branch_id'], $returnToUserId, $returnToBranchId, ACTION_RTS, STATUS_RTS, $remarks);
    log_status_change($doc['id'], $doc['current_status'], STATUS_RTS, $userId, $remarks);

    // Complete the returning holder's permanent processing record.
    complete_processing_record((int)$doc['id'], $userId, ACTION_RTS, STATUS_RTS, $returnToUserId, $returnToBranchId, $remarks);

    $pdo->prepare('UPDATE documents SET current_status = ?, current_holder_id = ?, current_branch_id = ?, updated_at = NOW() WHERE id = ?')
        ->execute([STATUS_RTS, $returnToUserId, $returnToBranchId, $doc['id']]);

    if ($returnToUserId) {
        notify((int)$returnToUserId, (int)$doc['id'], 'DOC-' . $doc['tracking_number'] . ' has been returned to you.');
    }

    audit('DOCUMENT_RTS', 'documents', $doc['id'], 'Returned to user ' . $returnToUserId, $userId);
    return true;
}

/**
 * Record a status history entry (immutable).
 */
function log_status_change(int $docId, ?string $old, string $new, int $changedBy, ?string $remarks): void
{
    db()->prepare(
        'INSERT INTO document_status_history (document_id, previous_status, new_status, changed_by, remarks)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$docId, $old, $new, $changedBy, $remarks]);
}

// ---------------------------------------------------------------------
// Tracking number generation (DOC-YYYY-NNNNNN)
// ---------------------------------------------------------------------
function next_tracking_number(PDO $pdo, int $year): string
{
    // Find the highest sequence for the given year to avoid collisions on re-seed.
    $stmt = $pdo->prepare("SELECT tracking_number FROM documents WHERE tracking_number LIKE ? ORDER BY tracking_number DESC LIMIT 1");
    $pattern = 'DOC-' . $year . '-%';
    $stmt->execute([$pattern]);
    $last = $stmt->fetchColumn();

    $seq = 1;
    if ($last && preg_match('/(\d{6})$/', (string)$last, $m)) {
        $seq = ((int)$m[1]) + 1;
    }

    // Guard against duplicates when many inserts happen fast.
    do {
        $tracking = sprintf('DOC-%d-%06d', $year, $seq);
        $check = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE tracking_number = ?');
        $check->execute([$tracking]);
        $seq++;
    } while ((int)$check->fetchColumn() > 0);

    return $tracking;
}

// ---------------------------------------------------------------------
// Fetch helpers
// ---------------------------------------------------------------------
function fetch_document(int $docId): ?array
{
    $stmt = db()->prepare('SELECT * FROM documents WHERE id = ? LIMIT 1');
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
    return $doc ?: null;
}

function fetch_user(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetch_branch(int $branchId): ?array
{
    $stmt = db()->prepare('SELECT * FROM branches WHERE id = ? LIMIT 1');
    $stmt->execute([$branchId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Return a document joined with origin branch, originator, holder names, holder branch.
 */
function document_with_relations(int $docId): ?array
{
    $sql = 'SELECT d.*,
                   ob.branch_name AS origin_branch_name,
                   cb.branch_name AS current_branch_name,
                   cr.full_name   AS created_by_name,
                   cu.full_name   AS holder_name,
                   cu.role        AS holder_role,
                   cu.position    AS holder_position
            FROM documents d
            LEFT JOIN branches ob ON ob.id = d.originating_branch_id
            LEFT JOIN branches cb ON cb.id = d.current_branch_id
            LEFT JOIN users cr ON cr.id = d.created_by
            LEFT JOIN users cu ON cu.id = d.current_holder_id
            WHERE d.id = ? LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([$docId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ---------------------------------------------------------------------
// Shared document-action dispatcher (for secretary deputy & CO processing)
// Returns an associative array: ['ok' => bool, 'redirect' => ?string, 'error' => ?string]
// Inspects $_POST and dispatches to receive / status / forward / rts.
// ---------------------------------------------------------------------
function handle_document_actions(array $user): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return ['ok' => true, 'redirect' => null, 'error' => null];
    }
    verify_csrf();

    $uid = (int)$user['id'];
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['doc_id'] ?? 0);
    $doc = $id > 0 ? fetch_document($id) : null;

    // CORE OWNERSHIP RULE: only the CURRENT holder may act on the document.
    if (!$doc || !is_current_holder($doc, $uid)) {
        return ['ok' => false, 'redirect' => null, 'error' => 'You cannot perform this action because this document is no longer assigned to you.'];
    }

    try {
        switch ($action) {
            case 'receive':
                // Only allow receiving documents that are awaiting receipt.
                if (!awaiting_receipt($id, $uid)) {
                    return ['ok' => false, 'redirect' => null, 'error' => 'This document is not waiting to be received.'];
                }
                receive_document($id, $uid);
                return ['ok' => true, 'redirect' => BASE_URL . '/view_document.php?id=' . $id, 'error' => null];

            case 'change_status':
                $newStatus = strtoupper((string)($_POST['new_status'] ?? ''));
                $remarks = trim((string)($_POST['remarks'] ?? ''));
                if ($newStatus === STATUS_RTS && $remarks === '') {
                    return ['ok' => false, 'redirect' => null, 'error' => 'Remarks/reason is required when returning to sender.'];
                }
                if (!in_array($newStatus, ['PENDING', 'SIGNED', 'APPROVED', 'RTS'], true)) {
                    return ['ok' => false, 'redirect' => null, 'error' => 'Invalid status.'];
                }
                change_status($id, $uid, $newStatus, $remarks ?: null);
                return ['ok' => true, 'redirect' => BASE_URL . '/view_document.php?id=' . $id, 'error' => null];

            case 'forward':
                $toUserId = (int)($_POST['to_user'] ?? 0);
                $remarks = trim((string)($_POST['remarks'] ?? ''));
                if ($toUserId <= 0) {
                    return ['ok' => false, 'redirect' => null, 'error' => 'Please select a destination user.'];
                }
                if ($toUserId === $uid) {
                    return ['ok' => false, 'redirect' => null, 'error' => 'You cannot forward a document to yourself.'];
                }
                $toUser = fetch_user($toUserId);
                if (!$toUser || $toUser['status'] !== 'active' || !$toUser['branch_id']) {
                    return ['ok' => false, 'redirect' => null, 'error' => 'Invalid destination user.'];
                }
                if (!can_route($user['role'], $toUser['role'])) {
                    return ['ok' => false, 'redirect' => null, 'error' => 'Unauthorized routing destination.'];
                }
                forward_document($id, $uid, $toUserId, $remarks);
                return ['ok' => true, 'redirect' => BASE_URL . '/view_document.php?id=' . $id, 'error' => null];

            default:
                return ['ok' => false, 'redirect' => null, 'error' => 'Unknown action.'];
        }
    } catch (Throwable $e) {
        log_error('handle_document_actions failed: ' . $e->getMessage());
        return ['ok' => false, 'redirect' => null, 'error' => 'Something went wrong. Please try again.'];
    }
}
