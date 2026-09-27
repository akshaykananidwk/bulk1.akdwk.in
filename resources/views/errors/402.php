<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>402 — <?= e(__('errors.402_title', 'Subscription expired')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-page">
    <div class="glass auth-card text-center">
        <div style="font-size:3rem">⛔</div>
        <h1><?= e(__('errors.402_title', 'Subscription expired')) ?></h1>
        <p class="text-muted"><?= e($message !== '' ? $message : __('billing.expired', 'Your subscription has expired. Please renew to continue.')) ?></p>
        <form method="post" action="/logout"><?= csrf_field() ?>
            <button class="btn btn-primary" type="submit"><?= e(__('auth.logout', 'Log out')) ?></button>
        </form>
    </div>
</div>
</body>
</html>
