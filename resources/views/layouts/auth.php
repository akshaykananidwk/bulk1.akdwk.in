<?php use App\Core\Layout; use App\Core\View; ?>
<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(Layout::title()) ?></title>
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth-page">
    <div class="glass auth-card">
        <div class="auth-logo">
            <span style="font-size:1.6rem">🦚</span>
            <span><?= e(setting('app_name', 'Krishna WhatsApp Cloud')) ?></span>
        </div>

        <?php View::partial('partials/alerts'); ?>

        <?php View::yield_('content'); ?>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(url('/assets/vendor/alpine.min.js')) ?>" defer></script>
</body>
</html>
