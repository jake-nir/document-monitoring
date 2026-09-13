<?php
/**
 * Entry point. Redirects to login (or dashboard if already logged in).
 */
require_once __DIR__ . '/includes/bootstrap.php';

start_secure_session();

if (current_user()) {
    redirect_by_role($_SESSION['role'] ?? '');
    exit;
}
header('Location: ' . BASE_URL . '/login.php');
exit;
