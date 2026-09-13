<?php
/**
 * Login page with login-attempt throttling, CSRF, and last-login tracking.
 */
require_once __DIR__ . '/includes/bootstrap.php';

start_secure_session();

if (current_user()) {
    redirect_by_role($_SESSION['role'] ?? '');
    exit;
}

$error = null;
$timeoutMsg = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $ip = client_ip();

        // Throttle: check recent failed attempts
        $max = max(3, (int)setting('max_login_attempts', '5'));
        $lockMins = (int)setting('lockout_minutes', '15');
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM login_attempts WHERE username = ? AND ip_address = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)');
        $stmt->execute([$username, $ip, $lockMins]);
        $recentFails = (int)$stmt->fetch()['c'];

        if ($recentFails >= $max) {
            // Locked out: record the blocked attempt but do not verify.
            record_login_attempt($username, $ip, false);
            record_login_log(null, $username, $ip, false);
            audit('LOGIN_LOCKED', 'auth', null, 'Account temporarily locked for ' . $username);
            $error = 'Too many failed login attempts. Please wait a few minutes and try again.';
        } else {
            $q = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
            $q->execute([$username]);
            $user = $q->fetch();

            $verified = $user && $user['status'] === 'active' && password_verify($password, $user['password']);
            // Record a single attempt row with the true outcome.
            record_login_attempt($username, $ip, $verified);
            record_login_log($verified ? (int)$user['id'] : null, $username, $ip, $verified);

            if ($verified) {
                audit('LOGIN_SUCCESS', 'auth', (int)$user['id'], 'User logged in.');
                login_user((int)$user['id']);
                redirect_by_role($user['role']);
                exit;
            } else {
                $error = 'Invalid username or password.';
                audit('LOGIN_FAILED', 'auth', $user['id'] ?? null, 'Failed login for ' . $username);
            }
        }
    }
}

require_once CONFIG_PATH . DS . 'auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | <?= e(setting('org_name', 'Document Monitoring System')) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/app.css">
</head>
<body class="login-bg">
<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5 col-lg-4">
            <div class="card login-card shadow-lg border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="login-logo mx-auto mb-3"><i class="bi bi-files"></i></div>
                        <h4 class="fw-bold mb-1"><?= e(setting('org_name', 'Document Monitoring System')) ?></h4>
                        <p class="text-muted small mb-0">Sign in to continue</p>
                    </div>

                    <?php if ($timeoutMsg): ?>
                        <div class="alert alert-warning small">Your session has expired. Please log in again.</div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger small"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Username" required autofocus autocomplete="username">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary fw-semibold py-2"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
                        </div>
                    </form>
                    <div class="text-center small text-muted mt-4">
                        <span>Demo: admin/admin123 &middot; juan/branch123 &middot; depsec/secretary123 &middot; sec/secretary123</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= BASE_URL ?>/public/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    var p = document.getElementById('password');
    p.type = (p.type === 'password') ? 'text' : 'password';
});
</script>
</body>
</html>
