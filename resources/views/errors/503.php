<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="20">
    <title>Maintenance in progress</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-page">
    <div class="glass auth-card text-center">
        <div style="font-size:3rem">🛠️</div>
        <h1><?= e(__('errors.503_title', 'Update in progress')) ?></h1>
        <p class="text-muted"><?= e(__('errors.503', 'We are applying an update. This page refreshes automatically — we will be back in a minute or two.')) ?></p>
        <div class="progress mt-4"><div class="progress-bar striped" style="width:100%"></div></div>
    </div>
</div>
</body>
</html>
