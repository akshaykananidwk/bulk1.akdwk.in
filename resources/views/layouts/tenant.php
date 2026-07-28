<?php
use App\Core\Auth;
use App\Core\Layout;
use App\Core\View;

$currentUser = user();
$currentTenant = tenant();
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
<?php if (Auth::isImpersonating()): ?>
    <div class="impersonation-banner">
        ⚠️ <?= e(__('admin.impersonating', 'You are logged in as')) ?> <strong><?= e($currentUser['name'] ?? '') ?></strong>
        (<?= e($currentTenant['name'] ?? '') ?>)
        <form method="post" action="<?= e(url('/impersonation/stop')) ?>" style="display:inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;margin-left:.5rem">
                <?= e(__('admin.stop_impersonating', 'Exit')) ?>
            </button>
        </form>
    </div>
<?php endif; ?>

<div class="app-shell">
    <?php View::partial('partials/sidebar-tenant'); ?>

    <div class="main">
        <?php View::partial('partials/topbar'); ?>

        <main class="content">
            <?php View::partial('partials/alerts'); ?>
            <?php View::yield_('content'); ?>
        </main>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/sse.js')) ?>"></script>
<script src="<?= e(url('/assets/vendor/sweetalert2.min.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(url('/assets/vendor/sweetalert2.min.css')) ?>">
<?php foreach (Layout::scripts() as $script): ?>
    <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
<script src="<?= e(url('/assets/vendor/alpine.min.js')) ?>" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.kwcRealtime) {
            kwcRealtime.connect(['inbox', 'notifications']);
            kwcRealtime.on('notification', function (payload) {
                if (payload && payload.title && window.kwc) { kwc.toast(payload.title, 'info'); }
            });
        }
    });
</script>
</body>
</html>
