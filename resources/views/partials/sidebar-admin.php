<?php
use App\Core\Request;

$path = Request::instance()->path();
$active = fn (string $prefix): string => str_starts_with($path, $prefix) ? 'active' : '';
?>
<aside class="sidebar" id="sidebar" aria-label="Admin navigation">
    <div class="brand">
        <span style="font-size:1.4rem">🦚</span>
        <span class="brand-name"><?= e(__('admin.brand', 'Super Admin')) ?></span>
    </div>

    <nav>
        <a class="nav-item <?= $path === '/admin' ? 'active' : $active('/admin/dashboard') ?>" href="<?= e(url('/admin')) ?>">
            <span class="icon">📊</span><span class="nav-label"><?= e(__('nav.dashboard', 'Dashboard')) ?></span>
        </a>
        <a class="nav-item <?= $active('/admin/tenants') ?>" href="<?= e(url('/admin/tenants')) ?>">
            <span class="icon">🏢</span><span class="nav-label"><?= e(__('admin.tenants', 'Tenants')) ?></span>
        </a>
        <a class="nav-item <?= $active('/admin/plans') ?>" href="<?= e(url('/admin/plans')) ?>">
            <span class="icon">📦</span><span class="nav-label"><?= e(__('admin.plans', 'Plans')) ?></span>
        </a>
        <a class="nav-item <?= $active('/admin/transactions') ?>" href="<?= e(url('/admin/transactions')) ?>">
            <span class="icon">💰</span><span class="nav-label"><?= e(__('admin.transactions', 'Transactions')) ?></span>
        </a>

        <div class="nav-section"><?= e(__('admin.section_platform', 'Platform')) ?></div>
        <a class="nav-item <?= $active('/admin/meta-app') ?>" href="<?= e(url('/admin/meta-app')) ?>">
            <span class="icon">📱</span><span class="nav-label"><?= e(__('admin.meta_app', 'Meta App')) ?></span>
        </a>
        <a class="nav-item <?= $active('/admin/settings') ?>" href="<?= e(url('/admin/settings')) ?>">
            <span class="icon">⚙️</span><span class="nav-label"><?= e(__('admin.settings', 'Global Settings')) ?></span>
        </a>
        <a class="nav-item <?= $active('/admin/pricing-rates') ?>" href="<?= e(url('/admin/pricing-rates')) ?>">
            <span class="icon">🏷️</span><span class="nav-label"><?= e(__('admin.pricing', 'Pricing Rates')) ?></span>
        </a>

        <div class="nav-section"><?= e(__('admin.section_system', 'System')) ?></div>
        <a class="nav-item <?= $active('/admin/queue') ?>" href="<?= e(url('/admin/queue')) ?>">
            <span class="icon">⏳</span><span class="nav-label"><?= e(__('admin.queue', 'Queue Monitor')) ?></span>
        </a>
        <a class="nav-item <?= $active('/admin/logs') ?>" href="<?= e(url('/admin/logs')) ?>">
            <span class="icon">📜</span><span class="nav-label"><?= e(__('admin.logs', 'Logs')) ?></span>
        </a>
        <a class="nav-item <?= $active('/admin/health') ?>" href="<?= e(url('/admin/health')) ?>">
            <span class="icon">❤️</span><span class="nav-label"><?= e(__('admin.health', 'System Health')) ?></span>
        </a>
        <a class="nav-item <?= $active('/admin/backups') ?>" href="<?= e(url('/admin/backups')) ?>">
            <span class="icon">💾</span><span class="nav-label"><?= e(__('admin.backups', 'Backups')) ?></span>
        </a>
        <a class="nav-item <?= $active('/admin/updates') ?>" href="<?= e(url('/admin/updates')) ?>">
            <span class="icon">🚀</span><span class="nav-label"><?= e(__('admin.updates', 'Updates')) ?></span>
        </a>
    </nav>
</aside>
