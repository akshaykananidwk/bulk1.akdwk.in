<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Forbidden</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-page">
    <div class="glass auth-card text-center">
        <div style="font-size:3rem">🚫</div>
        <h1>403</h1>
        <p class="text-muted"><?= e(__('errors.403', 'You do not have permission to access this page.')) ?></p>
        <a class="btn btn-primary" href="/">← <?= e(__('errors.go_home', 'Go home')) ?></a>
    </div>
</div>
</body>
</html>
