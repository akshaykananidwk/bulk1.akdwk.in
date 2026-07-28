<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<?php
$cronOk = $cronHeartbeat !== null && (time() - $cronHeartbeat) < 120;
$workerOk = $workerHeartbeat !== null && (time() - $workerHeartbeat) < 120;
?>
<div class="page-header">
    <h1><?= e(__('admin.queue', 'Queue Monitor')) ?></h1>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.pending_jobs', 'Pending jobs')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $pendingTotal)) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.failed_jobs', 'Failed jobs')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $failedTotal)) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.cron_heartbeat', 'Cron heartbeat')) ?></span>
        <span class="stat-value"><span class="badge badge-<?= $cronOk ? 'success' : 'danger' ?>"><?= $cronOk ? e(__('common.ok', 'OK')) : e(__('common.down', 'Down')) ?></span></span>
        <span class="stat-delta"><?= $cronHeartbeat !== null ? e(time_ago(date('Y-m-d H:i:s', $cronHeartbeat))) : e(__('admin.never_ran', 'never ran')) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.worker_heartbeat', 'Worker heartbeat')) ?></span>
        <span class="stat-value"><span class="badge badge-<?= $workerOk ? 'success' : 'danger' ?>"><?= $workerOk ? e(__('common.ok', 'OK')) : e(__('common.down', 'Down')) ?></span></span>
        <span class="stat-delta"><?= $workerHeartbeat !== null ? e(time_ago(date('Y-m-d H:i:s', $workerHeartbeat))) : e(__('admin.never_ran', 'never ran')) ?></span>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.queues', 'Queues')) ?></h2></div>
        <div class="table-wrap" style="border:none">
            <table class="table">
                <thead><tr>
                    <th><?= e(__('admin.queue_name', 'Queue')) ?></th>
                    <th><?= e(__('admin.pending', 'Pending')) ?></th>
                    <th><?= e(__('admin.reserved', 'Processing')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($byQueue as $row): ?>
                    <tr>
                        <td><?= e($row['queue']) ?></td>
                        <td class="tabular"><?= e(number_format((float) $row['n'])) ?></td>
                        <td class="tabular"><?= e(number_format((float) ($row['reserved'] ?? 0))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($byQueue)): ?>
                    <tr><td colspan="3" class="text-center text-muted"><?= e(__('admin.queue_empty', 'Queue is empty.')) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.recent_cron', 'Recent cron runs')) ?></h2></div>
        <div class="table-wrap" style="border:none">
            <table class="table">
                <thead><tr>
                    <th><?= e(__('admin.task', 'Task')) ?></th>
                    <th><?= e(__('fields.status', 'Status')) ?></th>
                    <th><?= e(__('admin.duration', 'Duration')) ?></th>
                    <th><?= e(__('admin.ran_at', 'Ran')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($recentCron as $row): ?>
                    <tr>
                        <td><?= e($row['task']) ?></td>
                        <td><span class="badge badge-<?= $row['status'] === 'success' ? 'success' : 'danger' ?>"><?= e($row['status']) ?></span></td>
                        <td class="tabular"><?= e(number_format((float) $row['duration_ms'])) ?> ms</td>
                        <td class="text-sm text-muted"><?= e(time_ago((string) $row['ran_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentCron)): ?>
                    <tr><td colspan="4" class="text-center text-muted"><?= e(__('admin.no_cron_runs', 'No cron runs logged yet.')) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h2 class="card-title"><?= e(__('admin.failed_jobs', 'Failed jobs')) ?> (<?= e(number_format((float) $failedTotal)) ?>)</h2>
        <?php if ($failedTotal > 0): ?>
            <div class="flex gap-2 items-center">
                <form method="post" action="<?= e(url('/admin/queue/retry')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-sm" type="submit">↻ <?= e(__('admin.retry_all', 'Retry all')) ?></button>
                </form>
                <form method="post" action="<?= e(url('/admin/queue/clear-failed')) ?>" data-confirm="<?= e(__('admin.clear_failed_confirm', 'Permanently delete all failed jobs?')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger btn-sm" type="submit">🗑 <?= e(__('admin.clear_failed', 'Clear failed')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <div class="table-wrap" style="border:none">
        <table class="table">
            <thead><tr>
                <th>#</th>
                <th><?= e(__('admin.queue_name', 'Queue')) ?></th>
                <th><?= e(__('admin.job', 'Job')) ?></th>
                <th><?= e(__('admin.exception', 'Exception')) ?></th>
                <th><?= e(__('admin.failed_at', 'Failed')) ?></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($failed as $job): ?>
                <tr>
                    <td class="tabular"><?= (int) $job['id'] ?></td>
                    <td><?= e($job['queue']) ?></td>
                    <td class="text-sm"><?= e($job['job_class']) ?></td>
                    <td class="text-sm">
                        <details>
                            <summary class="text-muted"><?= e(mb_substr((string) $job['exception'], 0, 80)) ?>…</summary>
                            <pre class="text-xs" style="white-space:pre-wrap;max-height:220px;overflow-y:auto"><?= e(mb_substr((string) $job['exception'], 0, 4000)) ?></pre>
                        </details>
                    </td>
                    <td class="text-sm text-muted"><?= e(time_ago((string) $job['failed_at'])) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('/admin/queue/retry/' . (int) $job['id'])) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline btn-sm" type="submit">↻ <?= e(__('admin.retry', 'Retry')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($failed)): ?>
                <tr><td colspan="6" class="text-center text-muted"><?= e(__('admin.no_failed', 'No failed jobs. 🎉')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php View::end(); ?>
