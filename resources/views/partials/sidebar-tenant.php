<?php
use App\Core\Auth;
use App\Core\Request;

$path = Request::instance()->path();
$active = fn (string $prefix): string => str_starts_with($path, $prefix) ? 'active' : '';
?>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
        <span style="font-size:1.4rem">🦚</span>
        <span class="brand-name"><?= e(setting('app_name', 'Krishna WhatsApp Cloud')) ?></span>
    </div>

    <nav>
        <a class="nav-item <?= $active('/tenant/dashboard') ?: ($path === '/tenant' ? 'active' : '') ?>" href="<?= e(url('/tenant')) ?>">
            <span class="icon">📊</span><span class="nav-label"><?= e(__('nav.dashboard', 'Dashboard')) ?></span>
        </a>
        <?php if (Auth::can('inbox.view')): ?>
        <a class="nav-item <?= $active('/tenant/inbox') ?>" href="<?= e(url('/tenant/inbox')) ?>">
            <span class="icon">💬</span><span class="nav-label"><?= e(__('nav.inbox', 'Team Inbox')) ?></span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('contacts.view')): ?>
        <a class="nav-item <?= $active('/tenant/contacts') ?>" href="<?= e(url('/tenant/contacts')) ?>">
            <span class="icon">👥</span><span class="nav-label"><?= e(__('nav.contacts', 'Contacts')) ?></span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('campaigns.view')): ?>
        <a class="nav-item <?= $active('/tenant/campaigns') ?>" href="<?= e(url('/tenant/campaigns')) ?>">
            <span class="icon">📣</span><span class="nav-label"><?= e(__('nav.campaigns', 'Campaigns')) ?></span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('templates.view')): ?>
        <a class="nav-item <?= $active('/tenant/templates') ?>" href="<?= e(url('/tenant/templates')) ?>">
            <span class="icon">📋</span><span class="nav-label"><?= e(__('nav.templates', 'Templates')) ?></span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('flows.view')): ?>
        <a class="nav-item <?= $active('/tenant/flows') ?>" href="<?= e(url('/tenant/flows')) ?>">
            <span class="icon">🤖</span><span class="nav-label"><?= e(__('nav.flows', 'Bot Flows')) ?></span>
        </a>
        <?php endif; ?>

        <div class="nav-section"><?= e(__('nav.section_settings', 'Setup')) ?></div>
        <?php if (Auth::can('whatsapp.view')): ?>
        <a class="nav-item <?= $active('/tenant/whatsapp') ?>" href="<?= e(url('/tenant/whatsapp')) ?>">
            <span class="icon">📱</span><span class="nav-label"><?= e(__('nav.whatsapp', 'WhatsApp')) ?></span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('team.view')): ?>
        <a class="nav-item <?= $active('/tenant/team') ?>" href="<?= e(url('/tenant/team')) ?>">
            <span class="icon">🧑‍💼</span><span class="nav-label"><?= e(__('nav.team', 'Team')) ?></span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('api.keys')): ?>
        <a class="nav-item <?= $active('/tenant/developers') ?>" href="<?= e(url('/tenant/developers')) ?>">
            <span class="icon">🔌</span><span class="nav-label"><?= e(__('nav.developers', 'API & Webhooks')) ?></span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('billing.view')): ?>
        <a class="nav-item <?= $active('/tenant/billing') ?>" href="<?= e(url('/tenant/billing')) ?>">
            <span class="icon">💳</span><span class="nav-label"><?= e(__('nav.billing', 'Billing')) ?></span>
        </a>
        <?php endif; ?>
        <?php if (Auth::can('settings.view')): ?>
        <a class="nav-item <?= $active('/tenant/settings') ?>" href="<?= e(url('/tenant/settings')) ?>">
            <span class="icon">⚙️</span><span class="nav-label"><?= e(__('nav.settings', 'Settings')) ?></span>
        </a>
        <?php endif; ?>
    </nav>
</aside>
