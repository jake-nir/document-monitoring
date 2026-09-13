<?php
/**
 * HTML document header + opening layout.
 * Expects $pageTitle and $activeNav to be defined before inclusion.
 */

if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . DS . 'config' . DS . 'constants.php';
}

$pgTitle = $pageTitle ?? 'Document Monitoring System';
$orgName = e(setting('org_name', 'Document Monitoring System'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pgTitle) ?> | <?= $orgName ?></title>
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/public/assets/img/favicon.svg">
<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/app.css">
<?php if (!empty($extraCss)): foreach ($extraCss as $css): ?>
<link rel="stylesheet" href="<?= BASE_URL . e($css) ?>">
<?php endforeach; endif; ?>
</head>
<body class="bg-default">
<div class="d-flex" id="app-wrapper">
