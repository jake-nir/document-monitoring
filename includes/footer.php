<?php
/**
 * HTML document closing layout.
 */
?>
<script>window.DMS_BASE_URL = '<?= e(BASE_URL) ?>'; window.DMS_POLL = <?= ($GLOBALS['dmsPoll'] ?? true) ? 'true' : 'false' ?>;</script>
<?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
<script src="<?= BASE_URL . e($js) ?>"></script>
<?php endforeach; endif; ?>
<script src="<?= BASE_URL ?>/public/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/assets/js/app.js"></script>
</body>
</html>