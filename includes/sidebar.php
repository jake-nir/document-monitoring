<?php
/**
 * Role-based sidebar navigation.
 * Expects: $activeNav (string), and a logged-in $user array via require_login().
 */
$currentRole = $user['role'] ?? ROLE_ADMIN;
$navItems = [];

// Common documents section
$navItems[] = ['type' => 'heading', 'label' => 'Main'];
$navItems[] = ['type' => 'link', 'href' => $dashUrl ?? '#', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'key' => 'dashboard'];

if ($currentRole === ROLE_BRANCH) {
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/branch/incoming.php', 'icon' => 'bi-inbox', 'label' => 'Incoming', 'key' => 'incoming', 'badge' => incoming_count((int)($user['id'] ?? 0))];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/branch/documents.php', 'icon' => 'bi-folder2-open', 'label' => 'My Documents', 'key' => 'documents'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/branch/create_document.php', 'icon' => 'bi-file-earmark-plus', 'label' => 'Log Document', 'key' => 'create'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/branch/returned.php', 'icon' => 'bi-arrow-return-left', 'label' => 'Returned', 'key' => 'returned'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/branch/documents.php?status=APPROVED', 'icon' => 'bi-check2-circle', 'label' => 'Completed', 'key' => 'completed'];
} else {
    // Secretary Deputy & Secretary CO share processing pages via a shared name.
    $secDir = ($currentRole === ROLE_SECRETARY_CO) ? 'secretary_co' : 'secretary_deputy';
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/' . $secDir . '/incoming.php', 'icon' => 'bi-inbox', 'label' => 'Incoming', 'key' => 'incoming', 'badge' => incoming_count((int)($user['id'] ?? 0))];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/' . $secDir . '/documents.php', 'icon' => 'bi-folder2-open', 'label' => 'My Documents', 'key' => 'documents'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/' . $secDir . '/copies.php', 'icon' => 'bi-clipboard-plus', 'label' => 'My Copies', 'key' => 'copies'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/' . $secDir . '/process.php', 'icon' => 'bi-arrow-left-right', 'label' => 'Process', 'key' => 'process'];
}

$navItems[] = ['type' => 'divider'];
$navItems[] = ['type' => 'heading', 'label' => 'Monitoring'];
$navItems[] = ['type' => 'link', 'href' => BASE_URL . '/reports/index.php', 'icon' => 'bi-bar-chart-line', 'label' => 'Reports', 'key' => 'reports'];

if ($currentRole === ROLE_ADMIN) {
    $navItems[] = ['type' => 'divider'];
    $navItems[] = ['type' => 'heading', 'label' => 'Administration'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/admin/users.php', 'icon' => 'bi-people', 'label' => 'Users', 'key' => 'users'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/admin/branches.php', 'icon' => 'bi-building', 'label' => 'Branches', 'key' => 'branches'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/admin/monitoring.php', 'icon' => 'bi-activity', 'label' => 'Live Monitoring', 'key' => 'monitoring'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/admin/audit_logs.php', 'icon' => 'bi-journal-text', 'label' => 'Audit Logs', 'key' => 'audit'];
    $navItems[] = ['type' => 'link', 'href' => BASE_URL . '/admin/settings.php', 'icon' => 'bi-gear', 'label' => 'Settings', 'key' => 'settings'];
}
?>
<aside class="sidebar text-white d-none d-md-flex flex-column flex-shrink-0">
    <div class="sidebar-brand d-flex align-items-center">
        <img src="<?= BASE_URL ?>/public/assets/img/logo-light.svg" alt="Logo" class="brand-logo">
        <span class="brand-name fw-bold">
            <?= e(setting('org_short', 'DMS')) ?>
            <span class="brand-sub">Document Monitoring</span>
        </span>
    </div>
    <hr class="mx-1 my-0">
    <ul class="nav nav-pills flex-column mb-auto py-2 small">
        <?php foreach ($navItems as $item): ?>
            <?php if (($item['type'] ?? '') === 'heading'): ?>
                <li class="nav-heading text-uppercase text-secondary px-3 pt-3 pb-1 small fw-bold"><?= e($item['label']) ?></li>
            <?php elseif (($item['type'] ?? '') === 'divider'): ?>
                <li><hr class="text-secondary mx-3 my-2"></li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link text-white <?= (($activeNav ?? '') === ($item['key'] ?? '')) ? 'active bg-primary' : '' ?>" href="<?= e($item['href']) ?>">
                        <i class="bi <?= e($item['icon']) ?> me-2"></i><?= e($item['label']) ?>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="badge bg-danger rounded-pill ms-1"><?= (int)$item['badge'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <div class="sidebar-user p-2 d-flex align-items-center gap-2">
        <span class="avatar"><?= e(initials($user['full_name'] ?? $user['username'] ?? 'U')) ?></span>
        <span class="text-truncate">
            <span class="name d-block text-truncate"><?= e($user['full_name'] ?? '') ?></span>
            <span class="role d-block text-truncate"><?= e(role_label($currentRole)) ?></span>
        </span>
    </div>
</aside>
