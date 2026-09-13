<?php
/**
 * API: Mark all notifications as read for the current user.
 * GET access (no CSRF needed for this low-risk state change; uses login session).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_login();

db()->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([(int)$user['id']]);
header('Location: ' . BASE_URL . '/' . (($_SESSION['role'] === ROLE_ADMIN) ? 'admin' : strtolower($_SESSION['role'])) . '/dashboard.php');
exit;
