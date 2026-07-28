<?php
use App\Core\Auth;
use App\Core\Layout;
use App\Core\View;

$appName = (string) setting('app_name', 'Krishna WhatsApp Cloud');
?>
<!DOCTYPE html>
<html lang="<?= e(\App\Core\Lang::locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(Layout::title()) ?></title>
    <meta name="description" content="<?= e(setting('app_tagline', 'WhatsApp Business Cloud API Platform')) ?>">
    <link rel="icon" href="<?= e(url('/favicon.ico')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style>
        .site-nav {
            position: sticky; top: 0; z-index: 50;
            display: flex; align-items: center; gap: 1rem;
            padding: .8rem 1.5rem;
            background: var(--glass); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }
        .site-nav .brand { font-weight: 700; font-size: 1.05rem; color: var(--text); display: flex; align-items: center; gap: .5rem; text-decoration: none; }
        .site-nav .links { display: flex; gap: 1.1rem; margin-left: 1rem; }
        .site-nav .links a { color: var(--text-muted); font-weight: 500; font-size: .9rem; }
        .site-nav .links a:hover { color: var(--primary); text-decoration: none; }
        .site-footer {
            border-top: 1px solid var(--border); margin-top: 4rem;
            padding: 2.5rem 1.5rem; background: var(--surface-2);
        }
        .site-footer .cols { max-width: 1080px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 2rem; }
        .site-footer h4 { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin-bottom: .6rem; }
        .site-footer a { display: block; color: var(--text-muted); font-size: .88rem; padding: .18rem 0; }
        .site-footer a:hover { color: var(--primary); text-decoration: none; }
        .site-main { min-height: 60vh; }
        .legal-page { max-width: 820px; margin: 0 auto; padding: 3rem 1.25rem; }
        .legal-page h1 { font-size: 1.7rem; margin-bottom: .25rem; }
        .legal-page h2 { font-size: 1.15rem; margin-top: 2rem; }
        .legal-page p, .legal-page li { color: var(--text); line-height: 1.75; font-size: .94rem; }
        .legal-page .updated { color: var(--text-muted); font-size: .82rem; margin-bottom: 2rem; }
        @media (max-width: 720px) {
            .site-nav .links { display: none; }
            .site-footer .cols { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<nav class="site-nav">
    <a class="brand" href="<?= e(url('/')) ?>"><span style="font-size:1.35rem">🦚</span> <?= e($appName) ?></a>
    <div class="links">
        <a href="<?= e(url('/#features')) ?>"><?= e(__('site.features', 'Features')) ?></a>
        <a href="<?= e(url('/pricing')) ?>"><?= e(__('site.pricing', 'Pricing')) ?></a>
        <a href="<?= e(url('/api/docs')) ?>"><?= e(__('site.api', 'API')) ?></a>
        <a href="<?= e(url('/contact')) ?>"><?= e(__('site.contact', 'Contact')) ?></a>
    </div>
    <div class="spacer" style="flex:1"></div>
    <?php if (Auth::check()): ?>
        <a class="btn btn-primary btn-sm" href="<?= e(url(Auth::isSuperAdmin() ? '/admin' : '/tenant')) ?>"><?= e(__('site.dashboard', 'Dashboard')) ?> →</a>
    <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/login')) ?>"><?= e(__('auth.login_button', 'Sign in')) ?></a>
        <?php if (setting('registration_enabled', '1') === '1'): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('/register')) ?>"><?= e(__('site.start_trial', 'Start free trial')) ?></a>
        <?php endif; ?>
    <?php endif; ?>
</nav>

<main class="site-main">
    <?php View::partial('partials/alerts'); ?>
    <?php View::yield_('content'); ?>
</main>

<footer class="site-footer">
    <div class="cols">
        <div>
            <div style="font-weight:700; font-size:1.05rem; margin-bottom:.5rem">🦚 <?= e($appName) ?></div>
            <p class="text-muted text-sm" style="max-width:340px"><?= e(setting('app_tagline', 'WhatsApp Business Cloud API Platform')) ?> — <?= e(__('site.footer_line', 'team inbox, broadcasts, chatbots and API on your own WhatsApp Business number.')) ?></p>
            <p class="text-muted text-xs">© <?= e(date('Y')) ?> <?= e($appName) ?> · Krishna SaaS Suite</p>
        </div>
        <div>
            <h4><?= e(__('site.product', 'Product')) ?></h4>
            <a href="<?= e(url('/pricing')) ?>"><?= e(__('site.pricing', 'Pricing')) ?></a>
            <a href="<?= e(url('/api/docs')) ?>"><?= e(__('site.api_docs', 'API documentation')) ?></a>
            <a href="<?= e(url('/register')) ?>"><?= e(__('site.start_trial', 'Start free trial')) ?></a>
            <a href="<?= e(url('/login')) ?>"><?= e(__('auth.login_button', 'Sign in')) ?></a>
        </div>
        <div>
            <h4><?= e(__('site.legal', 'Legal')) ?></h4>
            <a href="<?= e(url('/privacy-policy')) ?>"><?= e(__('site.privacy', 'Privacy Policy')) ?></a>
            <a href="<?= e(url('/terms')) ?>"><?= e(__('site.terms', 'Terms of Service')) ?></a>
            <a href="<?= e(url('/refund-policy')) ?>"><?= e(__('site.refund', 'Refund & Cancellation')) ?></a>
            <a href="<?= e(url('/data-deletion')) ?>"><?= e(__('site.data_deletion', 'Data Deletion')) ?></a>
            <a href="<?= e(url('/contact')) ?>"><?= e(__('site.contact', 'Contact Us')) ?></a>
        </div>
    </div>
</footer>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
