<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 — Server Error</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-page">
    <div class="glass auth-card text-center">
        <div style="font-size:3rem">⚠️</div>
        <h1>500</h1>
        <p class="text-muted"><?= e(__('errors.500', 'Something went wrong on our side. The team has been notified.')) ?></p>
        <?php if (isset($error) && $error instanceof \Throwable): ?>
            <div class="alert alert-danger text-sm" style="text-align:left; word-break:break-word">
                <div>
                    <strong><?= e(get_class($error)) ?></strong>: <?= e($error->getMessage()) ?><br>
                    <span class="text-xs"><?= e($error->getFile()) ?>:<?= e((string) $error->getLine()) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <a class="btn btn-primary" href="/">← <?= e(__('errors.go_home', 'Go home')) ?></a>
    </div>
</div>
</body>
</html>
