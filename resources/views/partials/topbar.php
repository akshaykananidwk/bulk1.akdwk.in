<?php
use App\Core\Layout;

$topbarUser = user();
$topbarTenant = tenant();
?>
<header class="topbar">
    <button class="btn btn-ghost btn-icon mobile-nav-toggle" data-sidebar-toggle aria-label="Toggle navigation">☰</button>

    <div>
        <?php $crumbs = Layout::breadcrumbs(); ?>
        <?php if ($crumbs): ?>
            <div class="breadcrumbs">
                <?php foreach ($crumbs as $i => $crumb): ?>
                    <?php if ($i > 0): ?><span>›</span><?php endif; ?>
                    <?php if (!empty($crumb['url'])): ?>
                        <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                    <?php else: ?>
                        <span><?= e($crumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="spacer"></div>

    <button class="btn btn-ghost btn-icon" onclick="kwc.toggleDark()" title="<?= e(__('nav.dark_mode', 'Toggle dark mode')) ?>" aria-label="Toggle dark mode">🌓</button>

    <div x-data="{ open: false }" style="position:relative">
        <button class="btn btn-ghost" x-on:click="open = !open" aria-haspopup="true">
            <span class="avatar avatar-sm"><?= e(\App\Core\Str::initials($topbarUser['name'] ?? '?')) ?></span>
            <span class="nav-label"><?= e($topbarUser['name'] ?? '') ?></span>
            <span aria-hidden="true">▾</span>
        </button>
        <div x-show="open" x-on:click.outside="open = false" x-cloak
             class="card" style="position:absolute; right:0; top:110%; min-width:190px; padding:.4rem; z-index:50">
            <a class="nav-item" href="<?= e(url('/profile')) ?>"><span class="icon">👤</span><span class="nav-label"><?= e(__('nav.profile', 'My profile')) ?></span></a>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="nav-item w-full" style="border:none;background:none;cursor:pointer;font:inherit;text-align:left">
                    <span class="icon">🚪</span><span class="nav-label"><?= e(__('nav.logout', 'Log out')) ?></span>
                </button>
            </form>
        </div>
    </div>
</header>
