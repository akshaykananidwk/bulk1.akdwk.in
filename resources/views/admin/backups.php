<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('admin.backups', 'Backups')) ?></h1>
    <form method="post" action="<?= e(url('/admin/backups/database')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit">＋ <?= e(__('admin.create_db_backup', 'Create database backup')) ?></button>
    </form>
</div>

<?php if ($dailyEnabled): ?>
    <div class="alert alert-info">
        <span>🕒</span>
        <div><?= e(__('admin.backup_daily_on', 'Daily automatic database backups are enabled. You can change this in Settings → System.')) ?></div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">
        <span>⚠️</span>
        <div><?= e(__('admin.backup_daily_off', 'Daily automatic backups are disabled. Enable them in Settings → System to get a database backup every night.')) ?></div>
    </div>
<?php endif; ?>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th><?= e(__('fields.type', 'Type')) ?></th>
            <th><?= e(__('admin.trigger', 'Trigger')) ?></th>
            <th><?= e(__('fields.file', 'File')) ?></th>
            <th><?= e(__('fields.size', 'Size')) ?></th>
            <th><?= e(__('fields.created', 'Created')) ?></th>
            <th><?= e(__('common.actions', 'Actions')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($backups as $backup): ?>
            <tr>
                <td><span class="badge badge-<?= $backup['type'] === 'database' ? 'info' : 'primary' ?>"><?= e($backup['type']) ?></span></td>
                <td class="text-sm text-muted"><?= e($backup['trigger_type']) ?></td>
                <td class="text-sm"><?= e(basename((string) $backup['path'])) ?></td>
                <td class="tabular"><?= e(\App\Core\Str::humanBytes((int) $backup['size_bytes'])) ?></td>
                <td class="text-sm text-muted"><?= e(time_ago((string) $backup['created_at'])) ?></td>
                <td>
                    <div class="flex gap-1 items-center">
                        <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/backups/' . (int) $backup['id'] . '/download')) ?>">⬇ <?= e(__('common.download', 'Download')) ?></a>
                        <form method="post" action="<?= e(url('/admin/backups/' . (int) $backup['id'] . '/delete')) ?>" data-confirm="<?= e(__('admin.backup_delete_confirm', 'Delete this backup file permanently?')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm" type="submit">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($backups)): ?>
            <tr><td colspan="6" class="text-center text-muted"><?= e(__('admin.no_backups', 'No backups yet. Create one now — it only takes a moment.')) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php View::end(); ?>
