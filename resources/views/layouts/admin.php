<?php
use App\Core\Layout;
use App\Core\View;

$currentUser = user();
$isDark = !empty($currentUser['dark_mode']);
?>
<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>" class="<?= $isDark ? 'dark' : '' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(Layout::title()) ?></title>
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php foreach (Layout::styles() as $style): ?>
        <link rel="stylesheet" href="<?= e($style) ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="app-shell">
    <?php View::partial('partials/sidebar-admin'); ?>

    <div class="main">
        <?php View::partial('partials/topbar'); ?>

        <main class="content">
            <?php View::partial('partials/alerts'); ?>
            <?php View::yield_('content'); ?>
        </main>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(url('/assets/vendor/sweetalert2.min.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(url('/assets/vendor/sweetalert2.min.css')) ?>">
<?php foreach (Layout::scripts() as $script): ?>
    <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
<script src="<?= e(url('/assets/vendor/alpine.min.js')) ?>" defer></script>
</body>
</html>
