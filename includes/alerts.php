<?php
/**
 * Flash/session alerts renderer.
 * Call set_flash() (in config/auth.php), then include this file once.
 */
if (!empty($_SESSION['flash'])): ?>
    <div class="px-3 pt-3">
        <?php foreach ($_SESSION['flash'] as $f): ?>
            <?php
            $cls = match ($f['type']) {
                'success' => 'alert-success',
                'danger'  => 'alert-danger',
                'warning' => 'alert-warning',
                default   => 'alert-info',
            };
            ?>
            <div class="alert <?= $cls ?> alert-dismissible fade show small py-2" role="alert">
                <?= e($f['message']) ?>
                <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>