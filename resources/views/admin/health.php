<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <div>
        <h1><?= e(__('admin.health', 'System Health')) ?></h1>
        <p class="text-sm text-muted" style="margin:0">
            <?= e(__('admin.app_version', 'App version')) ?> v<?= e($appVersion) ?>
            <?php if ($installedVersion !== '' && $installedVersion !== $appVersion): ?>
                · <?= e(__('admin.installed_version', 'installed')) ?> v<?= e($installedVersion) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <span class="stat-label">PHP</span>
        <span class="stat-value"><?= e($checks['php_version']) ?></span>
        <span class="stat-delta"><?= e(__('admin.memory_limit', 'memory')) ?> <?= e($checks['memory_limit']) ?> · <?= e(__('admin.upload_max', 'upload')) ?> <?= e($checks['upload_max']) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.db_version', 'Database')) ?></span>
        <span class="stat-value"><?= e($checks['db_version']) ?></span>
        <span class="stat-delta"><?= e(number_format($checks['db_size_mb'], 1)) ?> MB</span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.disk', 'Disk free')) ?></span>
        <span class="stat-value"><?= e(\App\Core\Str::humanBytes($checks['disk_free'])) ?></span>
        <span class="stat-delta"><?= e(__('common.of', 'of')) ?> <?= e(\App\Core\Str::humanBytes($checks['disk_total'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.stat_queue', 'Queue backlog')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $checks['queue_backlog'])) ?></span>
        <span class="stat-delta <?= (int) $checks['failed_jobs'] > 0 ? 'down' : '' ?>"><?= e(number_format((float) $checks['failed_jobs'])) ?> <?= e(__('admin.stat_failed', 'failed')) ?></span>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.checks', 'Checks')) ?></h2></div>
        <div class="table-wrap" style="border:none">
            <table class="table">
                <tbody>
                <tr>
                    <td><?= e(__('admin.check_cron', 'Cron running')) ?>
                        <div class="text-xs text-muted"><?= $checks['cron_last_run'] !== null ? e(__('admin.last_run', 'Last run')) . ' ' . e(time_ago(date('Y-m-d H:i:s', (int) $checks['cron_last_run']))) : e(__('admin.never_ran', 'never ran')) ?></div>
                    </td>
                    <td><span class="badge badge-<?= $checks['cron_ok'] ? 'success' : 'danger' ?>"><?= $checks['cron_ok'] ? '✅ OK' : '❌ ' . e(__('common.down', 'Down')) ?></span></td>
                </tr>
                <tr>
                    <td><?= e(__('admin.check_storage', 'Storage writable')) ?></td>
                    <td><span class="badge badge-<?= $checks['storage_writable'] ? 'success' : 'danger' ?>"><?= $checks['storage_writable'] ? '✅ OK' : '❌ ' . e(__('admin.not_writable', 'Not writable')) ?></span></td>
                </tr>
                <tr>
                    <td><?= e(__('admin.check_uploads', 'Uploads writable')) ?></td>
                    <td><span class="badge badge-<?= $checks['uploads_writable'] ? 'success' : 'danger' ?>"><?= $checks['uploads_writable'] ? '✅ OK' : '❌ ' . e(__('admin.not_writable', 'Not writable')) ?></span></td>
                </tr>
                <tr>
                    <td>HTTPS</td>
                    <td><span class="badge badge-<?= $checks['https'] ? 'success' : 'danger' ?>"><?= $checks['https'] ? '✅ OK' : '❌ ' . e(__('admin.insecure', 'Insecure')) ?></span></td>
                </tr>
                <tr>
                    <td><?= e(__('admin.check_maintenance', 'Maintenance mode')) ?></td>
                    <td><span class="badge badge-<?= $checks['maintenance'] ? 'warning' : 'success' ?>"><?= $checks['maintenance'] ? '⚠️ ' . e(__('common.on', 'ON')) : '✅ ' . e(__('common.off', 'Off')) ?></span></td>
                </tr>
                <tr>
                    <td>OPcache</td>
                    <td><span class="badge badge-<?= $checks['opcache'] ? 'success' : 'warning' ?>"><?= $checks['opcache'] ? '✅ ' . e(__('admin.enabled', 'Enabled')) : '❌ ' . e(__('admin.disabled', 'Disabled')) ?></span></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.extensions', 'PHP extensions')) ?></h2></div>
        <div class="grid-3">
            <?php foreach ($extensions as $name => $loaded): ?>
                <div class="flex gap-2 items-center justify-between">
                    <span class="text-sm"><?= e($name) ?></span>
                    <span class="badge badge-<?= $loaded ? 'success' : 'danger' ?>"><?= $loaded ? e(__('admin.loaded', 'loaded')) : e(__('admin.missing', 'missing')) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php View::end(); ?>
