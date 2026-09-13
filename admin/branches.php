<?php
/**
 * Admin: Branch management (add, edit, enable/disable, view branch users).
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$user = require_role(ROLE_ADMIN);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $code = strtoupper(trim((string)$_POST['branch_code'] ?? ''));
            $name = trim((string)$_POST['branch_name'] ?? '');
            $desc = trim((string)$_POST['description'] ?? '');
            if ($code === '' || $name === '') { set_flash('danger', 'Branch code and name are required.'); }
            else {
                $chk = $pdo->prepare('SELECT id FROM branches WHERE branch_code = ?'); $chk->execute([$code]);
                if ($chk->fetch()) { set_flash('danger', 'Branch code already exists.'); }
                else {
                    $pdo->prepare('INSERT INTO branches (branch_code, branch_name, description) VALUES (?,?,?)')->execute([$code, $name, $desc ?: null]);
                    audit('BRANCH_CREATED', 'branches', (int)$pdo->lastInsertId(), 'Created ' . $name);
                    set_flash('success', 'Branch "' . $name . '" created.');
                }
            }
            header('Location: branches.php'); exit;
        }
        elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $b = fetch_branch($id);
            if ($b) {
                $ns = $b['status'] === 'active' ? 'inactive' : 'active';
                $pdo->prepare('UPDATE branches SET status=? WHERE id=?')->execute([$ns, $id]);
                audit($ns==='active'?'BRANCH_ENABLED':'BRANCH_DISABLED', 'branches', $id, $b['branch_name']);
                set_flash('success', 'Branch "' . $b['branch_name'] . '" ' . ($ns==='active'?'enabled':'disabled') . '.');
            }
            header('Location: branches.php'); exit;
        }
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $b = fetch_branch($id);
            if ($b) {
                $name = trim((string)$_POST['branch_name'] ?? '');
                $desc = trim((string)$_POST['description'] ?? '');
                $pdo->prepare('UPDATE branches SET branch_name=?, description=? WHERE id=?')->execute([$name ?: $b['branch_name'], $desc ?: null, $id]);
                audit('BRANCH_MODIFIED', 'branches', $id, $b['branch_name']);
                set_flash('success', 'Branch updated.');
            }
            header('Location: branches.php'); exit;
        }
    } catch (Throwable $e) {
        log_error('branch action: ' . $e->getMessage());
        set_flash('danger', 'Something went wrong.');
        header('Location: branches.php'); exit;
    }
}

$branches = $pdo->query('SELECT * FROM branches ORDER BY branch_name')->fetchAll();
$userCounts = [];
foreach ($pdo->query('SELECT branch_id, COUNT(*) c FROM users WHERE branch_id IS NOT NULL GROUP BY branch_id') as $r) {
    $userCounts[(int)$r['branch_id']] = (int)$r['c'];
}

page_header('Branch Management', 'branches', $user);
?>
<h4 class="mb-3"><i class="bi bi-building me-2"></i>Branch Management</h4>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-light fw-semibold"><i class="bi bi-plus-circle me-2"></i>Add Branch</div>
    <div class="card-body">
        <form method="post" class="row g-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="col-md-2"><input name="branch_code" class="form-control form-control-sm" placeholder="Code e.g. BR-A" required></div>
            <div class="col-md-5"><input name="branch_name" class="form-control form-control-sm" placeholder="Branch Name" required></div>
            <div class="col-md-4"><input name="description" class="form-control form-control-sm" placeholder="Description"></div>
            <div class="col-md-1"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i></button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light fw-semibold">Branches &amp; Offices</div>
    <div class="card-body p-0">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light small"><tr><th>Code</th><th>Name</th><th>Description</th><th>Users</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($branches as $b): ?>
                <tr>
                    <td class="small fw-semibold"><?= e($b['branch_code']) ?></td>
                    <td class="small"><?= e($b['branch_name']) ?></td>
                    <td class="small text-muted"><?= e($b['description'] ?? '—') ?></td>
                    <td class="small"><?= (int)($userCounts[(int)$b['id']] ?? 0) ?> user(s)</td>
                    <td><?= $b['status']==='active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td class="small">
                        <button class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#bMod-<?= (int)$b['id'] ?>">Edit</button>
                        <form method="post" class="d-inline"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                            <button class="btn btn-sm <?= $b['status']==='active'?'btn-outline-danger':'btn-outline-success' ?> py-0"><?= $b['status']==='active'?'Disable':'Enable' ?></button>
                        </form>
                        <a class="btn btn-sm btn-outline-primary py-0" href="<?= BASE_URL ?>/admin/users.php?branch=<?= (int)$b['id'] ?>">Users</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($branches as $b): ?>
<div class="modal fade" id="bMod-<?= (int)$b['id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="post"><div class="modal-header"><h5 class="modal-title">Edit Branch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <div class="mb-2"><label class="form-label small fw-semibold">Branch Name</label><input name="branch_name" class="form-control form-control-sm" value="<?= e($b['branch_name']) ?>"></div>
        <div class="mb-2"><label class="form-label small fw-semibold">Description</label><input name="description" class="form-control form-control-sm" value="<?= e($b['description']) ?>"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Save</button></div>
</div></div></div>
</form>
<?php endforeach; ?>
<?php page_footer(); ?>
