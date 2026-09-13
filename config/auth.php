<?php
/**
 * Authentication, session, authorization, CSRF, and helper functions.
 */

declare(strict_types=1);

require_once CONFIG_PATH . DS . 'constants.php';

// ---------------------------------------------------------------------
// Error logging (internal only - never shown to users)
// ---------------------------------------------------------------------
function log_error(string $message): void
{
    $file = BASE_PATH . DS . 'logs' . DS . 'app.log';
    @mkdir(dirname($file), 0777, true);
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND);
}

/**
 * Returns a friendly error message; specifics are only logged.
 */
function friendly_error(string $logMessage): never
{
    log_error($logMessage);
    http_response_code(500);
    die('Something went wrong. Please try again.');
}

// ---------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

// ---------------------------------------------------------------------
// Client IP & user agent
// ---------------------------------------------------------------------
function client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function client_user_agent(): string
{
    return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
}

// ---------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        log_error('CSRF validation failed from ' . client_ip());
        die('Security validation failed. Please go back and try again.');
    }
}

// ---------------------------------------------------------------------
// Output escaping (XSS protection)
// ---------------------------------------------------------------------
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------
// Session bootstrap & timeout
// ---------------------------------------------------------------------
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookieParams['path'],
        'domain'   => $cookieParams['domain'],
        'secure'   => false,     // LAN http
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('DMSSESSID');
    session_start();
}

/**
 * Enforce session inactivity timeout.
 */
function enforce_session_timeout(): void
{
    $timeout = (int)setting('session_timeout_minutes', '30') * 60;
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    if ($lastActivity && (time() - $lastActivity > $timeout)) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ---------------------------------------------------------------------
// Login / user
// ---------------------------------------------------------------------
function login_user(int $userId): void
{
    $row = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $row->execute([$userId]);
    $user = $row->fetch();

    if (!$user || $user['status'] !== 'active') {
        return;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']  = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name']= $user['full_name'];
    $_SESSION['role']     = $user['role'];

    // Update last login
    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
}

/**
 * Current authenticated user row (fresh from DB) or null.
 */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return ($user && $user['status'] === 'active') ? $user : null;
}

/**
 * Require login on a protected page. Redirects to login if needed.
 */
function require_login(): array
{
    start_secure_session();
    enforce_session_timeout();
    $user = current_user();
    if (!$user) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    return $user;
}

/**
 * Require a specific role (or list of roles).
 */
function require_role(array|string $roles): array
{
    $user = require_login();
    $allowed = (array)$roles;
    if (!in_array($user['role'], $allowed, true)) {
        http_response_code(403);
        die('Access denied: You do not have permission to view this page.');
    }
    return $user;
}

function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

// ---------------------------------------------------------------------
// Settings helper
// ---------------------------------------------------------------------
function setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!$cache) {
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM system_settings') as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Throwable $e) {
            log_error('settings read failed: ' . $e->getMessage());
        }
    }
    return $cache[$key] ?? $default;
}

// ---------------------------------------------------------------------
// Audit logging
// ---------------------------------------------------------------------
function audit(string $action, string $module = null, ?int $recordId = null, string $details = null, ?int $userId = null): void
{
    $uid = $userId ?? (current_user_id() ?: null);
    try {
        db()->prepare(
            'INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$uid, $action, $module, $recordId, $details, client_ip(), client_user_agent()]);
    } catch (Throwable $e) {
        log_error('audit write failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// Login logging & attempt recording
// ---------------------------------------------------------------------
function record_login_attempt(string $username, string $ip, bool $success): void
{
    try {
        db()->prepare('INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)')
            ->execute([$username, $ip, $success ? 1 : 0]);
    } catch (Throwable $e) {
        log_error('login_attempt write failed: ' . $e->getMessage());
    }
}

function record_login_log(?int $userId, ?string $username, string $ip, bool $success): void
{
    try {
        db()->prepare('INSERT INTO login_logs (user_id, username, ip_address, user_agent, success) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $username, $ip, client_user_agent(), $success ? 1 : 0]);
    } catch (Throwable $e) {
        log_error('login_log write failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------------
function notify(int $userId, int $documentId, string $message): void
{
    try {
        db()->prepare(
            'INSERT INTO notifications (user_id, document_id, message) VALUES (?, ?, ?)'
        )->execute([$userId, $documentId, $message]);
    } catch (Throwable $e) {
        log_error('notification write failed: ' . $e->getMessage());
    }
}

function unread_notification_count(int $userId): int
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetch()['c'];
    } catch (Throwable $e) {
        return 0;
    }
}
