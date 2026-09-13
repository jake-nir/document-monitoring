<?php
/**
 * Admin: User management (add, edit, disable, enable, reset password).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_ADMIN);

$pdo = db();
$errors = [];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $fullName = trim((string)($_POST['full_name'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $confirm  = (string)($_POST['confirm_password'] ?? '');
            $role     = $_POST['role'] ?? '';
            $position = trim((string)($_POST['position'] ?? ''));
            $branchId = (int)($_POST['branch_id'] ?? 0) ?: null;
            $status   = $_POST['status'] === 'active' ? 'active' : 'inactive';

            if ($fullName === '' || $username === '' || $password === '') { $errors[] = 'Full name, username, and password are required.'; }
            elseif ($password !== $confirm) { $errors[] = 'Passwords do not match.'; }
            elseif (strlen($password) < 6) { $errors[] = 'Password must be at least 6 characters.'; }
            elseif (!in_array($role, [ROLE_BRANCH, ROLE_SECRETARY_DEPUTY, ROLE_SECRETARY_CO], true)) { $errors[] = 'Invalid role selected.'; }
            else {
                $chk = $pdo->prepare('SELECT id FROM users WHERE username = ?');
                $chk->execute([$username]);
                if ($chk->fetch()) { $errors[] = 'Username already exists.'; }
                else {
                    $pdo->prepare('INSERT INTO users (branch_id, full_name, username, password, role, position, status) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$branchId, $fullName, $username, password_hash($password, PASSWORD_DEFAULT), $role, $position ?: null, $status]);
                    $newId = (int)$pdo->lastInsertId();
                    audit('USER_CREATED', 'users', $newId, 'Created user ' . $username);
                    set_flash('success', 'User "' . $username . '" created.');
                    header('Location: users.php'); exit;
                }
            }
        }

        elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $target = fetch_user($id);
            if ($target && $id !== (int)$user['id']) {
                $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
                $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$newStatus, $id]);
                audit(($newStatus==='active'?'USER_ENABLED':'USER_DISABLED'), 'users', $id, 'User ' . $target['username']);
                set_flash('success', 'User "' . $target['username'] . '" ' . ($newStatus==='active'?'enabled':'disabled') . '.');
            } elseif ($id === (int)$user['id']) {
                set_flash('warning', 'You cannot disable your own account.');
            }
            header('Location: users.php'); exit;
        }

        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $target = fetch_user($id);
            if ($target) {
                $fullName = trim((string)($_POST['full_name'] ?? ''));
                $role     = $_POST['role'] ?? '';
                $position = trim((string)($_POST['position'] ?? ''));
                $branchId = (int)($_POST['branch_id'] ?? 0) ?: null;
                $status   = $_POST['status'] === 'active' ? 'active' : 'inactive';
                if (!in_array($role, [ROLE_BRANCH, ROLE_SECRETARY_DEPUTY, ROLE_SECRETARY_CO, ROLE_ADMIN], true)) { $role = $target['role']; }
                $pdo->prepare('UPDATE users SET full_name=?, role=?, position=?, branch_id=?, status=? WHERE id=?')
                    ->execute([$fullName, $role, $position ?: null, $branchId, $status, $id]);
                audit('USER_MODIFIED', 'users', $id, 'Updated user ' . $target['username']);
                set_flash('success', 'User "' . $target['username'] . '" updated.');
            }
            header('Location: users.php'); exit;
        }

        elseif ($action === 'reset_password') {
            $id = (int)($_POST['id'] ?? 0);
            $target = fetch_user($id);
            $newPass = (string)($_POST['new_password'] ?? '');
            if ($target && strlen($newPass) >= 6) {
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
                audit('USER_PASSWORD_RESET', 'users', $id, 'Password reset for ' . $target['username']);
                set_flash('success', 'Password for "' . $target['username'] . '" reset.');
            } else {
                set_flash('danger', 'New password must be at least 6 characters.');
            }
            header('Location: users.php'); exit;
        }
    } catch (Throwable $e) {
        log_error('users action: ' . $e->getMessage());
        $errors[] = 'Something went wrong. Please try again.';
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$where = '1=1';
$params = [];
if ($search !== '') { $where .= ' AND (u.username LIKE ? OR u.full_name LIKE ?)'; $like="%$search%"; array_push($params,$like,$like); }
$stmt = $pdo->prepare("SELECT u.*, b.branch_name FROM users u LEFT JOIN branches b ON b.id=u.branch_id WHERE $where ORDER BY u.full_name");
$stmt->execute($params);
$users = $stmt->fetchAll();

$branches = $pdo->query('SELECT * FROM branches WHERE status="active" ORDER BY branch_name')->fetchAll();

page_header('User Management', 'users', $user);
?>
<h4 class="mb-3"><i class="bi bi-people me-2"></i>User Management</h4>

<?php if ($errors): ?><div class="alert alert-danger small"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<!-- Add user -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-light fw-semibold"><i class="bi bi-person-plus me-2"></i>Add New User</div>
    <div class="card-body">
        <form method="post" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="col-md-3"><input name="full_name" class="form-control form-control-sm" placeholder="Full Name" value="<?= e($_POST['full_name'] ?? '') ?>"></div>
            <div class="col-md-2"><input name="username" class="form-control form-control-sm" placeholder="Username" value="<?= e($_POST['username'] ?? '') ?>"></div>
            <div class="col-md-2"><input name="password" type="password" class="form-control form-control-sm" placeholder="Password"></div>
            <div class="col-md-2"><input name="confirm_password" type="password" class="form-control form-control-sm" placeholder="Confirm"></div>
            <div class="col-md-2">
                <select name="role" class="form-select form-select-sm">
                    <option value="BRANCH">Branch</option>
                    <option value="SECRETARY_DEPUTY">Secretary Deputy</option>
                    <option value="SECRETARY_CO">Secretary of CO</option>
                </select>
            </div>
            <div class="col-md-2"><input name="position" class="form-control form-control-sm" placeholder="Position"></div>
            <div class="col-md-3">
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="">-- Branch/Office (optional) --</option>
                    <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['branch_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm"><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Add</button></div>
        </form>
    </div>
</div>

<!-- User list -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Users</span>
        <form method="get" class="d-flex gap-2">
            <input name="q" value="<?= e($search) ?>" class="form-control form-control-sm" placeholder="Search by name/username">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light small"><tr>
                <th>Full Name</th><th>Username</th><th>Role</th><th>Branch/Office</th><th>Position</th><th>Status</th><th>Last Login</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php $roleColor = match($u['role']) { 'ADMIN'=>'text-danger', 'BRANCH'=>'text-primary', 'SECRETARY_DEPUTY'=>'text-info', 'SECRETARY_CO'=>'text-success', default=>'' }; ?>
                <tr>
                    <td class="small"><?= e($u['full_name']) ?></td>
                    <td class="small"><?= e($u['username']) ?></td>
                    <td class="small <?= $roleColor ?> fw-semibold"><?= e(role_label($u['role'])) ?></td>
                    <td class="small"><?= e($u['branch_name'] ?? '—') ?></td>
                    <td class="small"><?= e($u['position'] ?? '—') ?></td>
                    <td><?= $u['status']==='active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td class="small text-muted"><?= $u['last_login'] ? e(date('M d, Y g:iA', strtotime($u['last_login']))) : '—' ?></td>
                    <td class="small">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editModal-<?= (int)$u['id'] ?>">Edit</button>
                            <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                            <form method="post" class="d-inline"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm <?= $u['status']==='active'?'btn-outline-danger':'btn-outline-success' ?> py-0"><?= $u['status']==='active'?'Disable':'Enable' ?></button>
                            </form>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-primary py-0" data-bs-toggle="modal" data-bs-target="#pwModal-<?= (int)$u['id'] ?>">Reset PW</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit modals -->
<?php foreach ($users as $u): ?>
<div class="modal fade" id="editModal-<?= (int)$u['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="post"><div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <div class="mb-2"><label class="form-label small fw-semibold">Full Name</label><input name="full_name" class="form-control form-control-sm" value="<?= e($u['full_name']) ?>"></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Role</label>
            <select name="role" class="form-select form-select-sm">
                <option value="BRANCH" <?= $u['role']==='BRANCH'?'selected':'' ?>>Branch</option>
                <option value="SECRETARY_DEPUTY" <?= $u['role']==='SECRETARY_DEPUTY'?'selected':'' ?>>Secretary Deputy</option>
                <option value="SECRETARY_CO" <?= $u['role']==='SECRETARY_CO'?'selected':'' ?>>Secretary of CO</option>
                <option value="ADMIN" <?= $u['role']==='ADMIN'?'selected':'' ?>>Admin</option>
            </select>
        </div>
        <div class="mb-2"><label class="form-label small fw-semibold">Position</label><input name="position" class="form-control form-control-sm" value="<?= e($u['position']) ?>"></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Branch/Office</label>
            <select name="branch_id" class="form-select form-select-sm">
                <option value="">-- None --</option>
                <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$u['branch_id']===(int)$b['id']?'selected':'' ?>><?= e($b['branch_name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2"><label class="form-label small fw-semibold">Status</label>
            <select name="status" class="form-select form-select-sm"><option value="active" <?= $u['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $u['status']==='inactive'?'selected':'' ?>>Inactive</option></select>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Save</button></div>
</div></div></div>
</form>

<div class="modal fade" id="pwModal-<?= (int)$u['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="post"><div class="modal-header"><h5 class="modal-title">Reset Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <p class="small text-muted">Reset password for <?= e($u['username']) ?>?</p>
        <input name="new_password" type="password" class="form-control form-control-sm" placeholder="New password (min 6 chars)" required>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Reset</button></div>
</div></div></div>
</form>
<?php endforeach; ?>

<?php page_footer(); ?>
