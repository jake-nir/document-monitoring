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
<aside class="sidebar bg-dark text-white d-none d-md-flex flex-column flex-shrink-0">
    <div class="sidebar-brand py-3 px-3 d-flex align-items-center">
        <i class="bi bi-files me-2"></i>
        <span class="fw-bold"><?= e(setting('org_short', 'DMS')) ?></span>
    </div>
    <hr class="text-secondary m-0">
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
    <div class="p-3 border-top small">
        <div class="text-white-50 mb-1">Logged in as</div>
        <div class="text-white fw-semibold"><?= e($user['full_name'] ?? '') ?></div>
        <div class="text-white-50"><?= e(role_label($currentRole)) ?></div>
    </div>
</aside>
