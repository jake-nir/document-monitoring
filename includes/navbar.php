<?php
/**
 * Top navigation bar with unread notification bell + user menu.
 */
$unread = incoming_count((int)($user['id'] ?? 0));
if ($unread > 0) {
    $stmt = db()->prepare('
        SELECT n.id, n.document_id, n.message, n.is_read, n.created_at, d.tracking_number
        FROM notifications n
        LEFT JOIN documents d ON d.id = n.document_id
        WHERE n.user_id = ? AND n.is_read = 0
        ORDER BY n.created_at DESC LIMIT 8
    ');
    $stmt->execute([(int)$user['id']]);
    $notifs = $stmt->fetchAll();
} else {
    $notifs = [];
}
?>
<nav class="navbar navbar-expand navbar-light bg-white border-bottom px-3">
    <button class="btn btn-outline-secondary d-md-none me-2" type="button" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <span class="navbar-brand fw-semibold mb-0 h6 d-none d-md-block"><?= e(setting('org_name', 'Document Monitoring System')) ?></span>

    <div class="ms-auto d-flex align-items-center">
        <!-- Notifications -->
        <div class="dropdown me-3">
            <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell"></i>
                <?php if ($unread > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge"><?= $unread ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow" style="width: 320px;">
                <li class="dropdown-header fw-bold">Notifications</li>
                <?php if ($notifs): ?>
                    <?php foreach ($notifs as $n): ?>
                        <li>
                            <a class="dropdown-item small text-wrap" href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$n['document_id'] ?>">
                            <?php /* fallback; real per-role view handled via role redirect below */ ?>
                            <?= e($n['message']) ?>
                                <div class="text-muted small"><?= e(date('M d, Y g:i A', strtotime($n['created_at']))) ?></div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item small text-primary" href="<?= BASE_URL ?>/api/mark_read.php">Mark all as read</a></li>
                <?php else: ?>
                    <li><span class="dropdown-item text-muted small">No unread notifications.</span></li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- User menu -->
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-1"></i><?= e($user['full_name'] ?? '') ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><span class="dropdown-item-text small text-muted"><?= e(role_label($user['role'] ?? '')) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
