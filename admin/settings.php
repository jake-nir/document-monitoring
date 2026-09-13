<?php
/**
 * Admin: System settings (aging thresholds, session timeout, org name, etc).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_ADMIN);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fields = [
        'aging_normal' => (string)($_POST['aging_normal'] ?? ''),
        'aging_attention' => (string)($_POST['aging_attention'] ?? ''),
        'session_timeout_minutes' => (string)($_POST['session_timeout_minutes'] ?? ''),
        'max_login_attempts' => (string)($_POST['max_login_attempts'] ?? ''),
        'lockout_minutes' => (string)($_POST['lockout_minutes'] ?? ''),
        'org_name' => trim((string)($_POST['org_name'] ?? '')),
        'org_short' => trim((string)($_POST['org_short'] ?? '')),
    ];
    foreach ($fields as $key => $val) {
        if ($val === '') continue;
        $pdo->prepare('UPDATE system_settings SET setting_value=? WHERE setting_key=?')->execute([$val, $key]);
    }
    audit('SETTINGS_UPDATED', 'settings', null, 'System settings updated.');
    set_flash('success', 'Settings saved.');
    header('Location: settings.php'); exit;
}

page_header('System Settings', 'settings', $user);
?>
<h4 class="mb-3"><i class="bi bi-gear me-2"></i>System Settings</h4>
<form method="post" class="card shadow-sm border-0">
    <div class="card-body">
        <?= csrf_field() ?>
        <h6 class="text-secondary mb-3"><i class="bi bi-building me-1"></i>Organization</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label small fw-semibold">Organization Name</label><input name="org_name" class="form-control" value="<?= e(setting('org_name')) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Short Name</label><input name="org_short" class="form-control" value="<?= e(setting('org_short')) ?>"></div>
        </div>
        <h6 class="text-secondary mb-3"><i class="bi bi-hourglass-split me-1"></i>Document Aging</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><label class="form-label small fw-semibold">Normal up to (days)</label><input name="aging_normal" type="number" min="1" class="form-control" value="<?= e(setting('aging_normal','2')) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Attention up to (days)</label><input name="aging_attention" type="number" min="1" class="form-control" value="<?= e(setting('aging_attention','4')) ?>"></div>
        </div>
        <h6 class="text-secondary mb-3"><i class="bi bi-shield-lock me-1"></i>Security</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><label class="form-label small fw-semibold">Session Timeout (minutes)</label><input name="session_timeout_minutes" type="number" min="5" class="form-control" value="<?= e(setting('session_timeout_minutes','30')) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Max Login Attempts</label><input name="max_login_attempts" type="number" min="3" class="form-control" value="<?= e(setting('max_login_attempts','5')) ?>"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold">Lockout (minutes)</label><input name="lockout_minutes" type="number" min="5" class="form-control" value="<?= e(setting('lockout_minutes','15')) ?>"></div>
        </div>
    </div>
    <div class="card-footer bg-light text-end"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Settings</button></div>
</form>
<?php page_footer(); ?>
