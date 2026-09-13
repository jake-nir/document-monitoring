<?php
/**
 * Logout.
 */
require_once __DIR__ . '/includes/bootstrap.php';
start_secure_session();

if (current_user_id()) {
    audit('LOGOUT', 'auth', current_user_id(), 'User logged out.');
}
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
