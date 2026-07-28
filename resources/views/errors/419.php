<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 — Session Expired</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-page">
    <div class="glass auth-card text-center">
        <div style="font-size:3rem">⏰</div>
        <h1>419</h1>
        <p class="text-muted"><?= e(__('errors.419', 'Your session expired. Please go back and try again.')) ?></p>
        <a class="btn btn-primary" href="javascript:history.back()">← <?= e(__('errors.go_back', 'Go back')) ?></a>
    </div>
</div>
</body>
</html>
