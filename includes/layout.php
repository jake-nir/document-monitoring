<?php
/**
 * Layout helper: opens a page with header, navbar, sidebar, and content wrapper.
 *
 * Usage:
 *   $user = require_role([...]);
 *   page_header('Page Title', 'active-nav-key', $user);
 *   ... content ...
 *   page_footer();
 */

function page_header(string $title, string $activeNav, array $user, array $extraJs = [], array $extraCss = []): void
{
    $pageTitle = $title;
    $activeNav = $activeNav;
    $dashUrl = BASE_URL . '/' . strtolower($user['role'] === 'SECRETARY_CO' ? 'secretary_co' : ($user['role'] === 'SECRETARY_DEPUTY' ? 'secretary_deputy' : strtolower($user['role']))) . '/dashboard.php';
    if ($user['role'] === 'ADMIN') {
        $dashUrl = BASE_URL . '/admin/dashboard.php';
    }
    include INCLUDES_PATH . DS . 'header.php';
    echo '<div class="main-wrapper d-flex flex-grow-1">';
    include INCLUDES_PATH . DS . 'sidebar.php';
    echo '<div class="content d-flex flex-column flex-grow-1">';
    include INCLUDES_PATH . DS . 'navbar.php';
    echo '<main class="p-3 flex-grow-1">';
    include INCLUDES_PATH . DS . 'alerts.php';
}

function page_footer(): void
{
    echo '</main>';
    echo '</div>'; // content
    echo '</div>'; // main-wrapper
    echo '</div>'; // app-wrapper
    include INCLUDES_PATH . DS . 'footer.php';
}