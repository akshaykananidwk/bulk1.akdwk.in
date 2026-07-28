<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('admin.logs', 'Logs')) ?></h1>
</div>

<div class="flex gap-2 items-center mb-2" style="flex-wrap:wrap">
    <?php foreach (['audit' => __('admin.logs_audit', 'Audit'), 'webhook' => __('admin.logs_webhook', 'Webhooks'), 'api' => __('admin.logs_api', 'API'), 'error' => __('admin.logs_error', 'Errors'), 'cron' => __('admin.logs_cron', 'Cron'), 'login' => __('admin.logs_login', 'Logins')] as $tab => $label): ?>
        <a class="btn btn-sm <?= $type === $tab ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(url('/admin/logs') . '?type=' . $tab) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="table-wrap">
    <table class="table">
        <?php if ($type === 'webhook'): ?>
            <thead><tr>
                <th><?= e(__('admin.source', 'Source')) ?></th>
                <th><?= e(__('admin.direction', 'Direction')) ?></th>
                <th><?= e(__('fields.status', 'Status')) ?></th>
                <th><?= e(__('admin.event', 'Event')) ?></th>
                <th><?= e(__('admin.payload', 'Payload')) ?></th>
                <th><?= e(__('fields.created', 'Created')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td><?= e($row['source']) ?></td>
                    <td><span class="badge badge-<?= $row['direction'] === 'in' ? 'info' : 'primary' ?>"><?= e($row['direction']) ?></span></td>
                    <td>
                        <?php $badge = match ($row['status']) { 'processed', 'delivered' => 'success', 'failed' => 'danger', 'processing' => 'warning', default => 'muted' }; ?>
                        <span class="badge badge-<?= e($badge) ?>"><?= e($row['status']) ?></span>
                    </td>
                    <td class="text-sm"><?= $row['event'] !== null && $row['event'] !== '' ? e($row['event']) : '—' ?></td>
                    <td class="text-sm">
                        <?php if ($row['payload'] !== null && $row['payload'] !== ''): ?>
                            <details>
                                <summary class="text-muted"><?= e(mb_substr((string) $row['payload'], 0, 60)) ?>…</summary>
                                <pre class="text-xs" style="white-space:pre-wrap;max-height:220px;overflow-y:auto"><?= e(mb_substr((string) $row['payload'], 0, 4000)) ?></pre>
                            </details>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $row['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        <?php elseif ($type === 'api'): ?>
            <thead><tr>
                <th><?= e(__('admin.method', 'Method')) ?></th>
                <th><?= e(__('admin.path', 'Path')) ?></th>
                <th><?= e(__('admin.status_code', 'Code')) ?></th>
                <th>IP</th>
                <th><?= e(__('fields.created', 'Created')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td><span class="badge badge-muted"><?= e($row['method']) ?></span></td>
                    <td class="text-sm"><?= e($row['path']) ?></td>
                    <td><span class="badge badge-<?= (int) $row['status_code'] < 400 ? 'success' : 'danger' ?>"><?= e((string) $row['status_code']) ?></span></td>
                    <td class="text-sm text-muted"><?= e($row['ip'] ?? '—') ?></td>
                    <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $row['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        <?php elseif ($type === 'error'): ?>
            <thead><tr>
                <th><?= e(__('admin.level', 'Level')) ?></th>
                <th><?= e(__('admin.message', 'Message')) ?></th>
                <th><?= e(__('admin.location', 'Location')) ?></th>
                <th><?= e(__('fields.created', 'Created')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td><span class="badge badge-<?= $row['level'] === 'error' ? 'danger' : 'warning' ?>"><?= e($row['level']) ?></span></td>
                    <td class="text-sm"><?= e(mb_substr((string) $row['message'], 0, 160)) ?></td>
                    <td class="text-xs text-muted"><?= $row['file'] ? e($row['file'] . ':' . $row['line']) : '—' ?></td>
                    <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $row['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        <?php elseif ($type === 'cron'): ?>
            <thead><tr>
                <th><?= e(__('admin.task', 'Task')) ?></th>
                <th><?= e(__('fields.status', 'Status')) ?></th>
                <th><?= e(__('admin.duration', 'Duration')) ?></th>
                <th><?= e(__('admin.ran_at', 'Ran')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td><?= e($row['task']) ?></td>
                    <td><span class="badge badge-<?= $row['status'] === 'success' ? 'success' : 'danger' ?>"><?= e($row['status']) ?></span></td>
                    <td class="tabular"><?= e(number_format((float) $row['duration_ms'])) ?> ms</td>
                    <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $row['ran_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        <?php elseif ($type === 'login'): ?>
            <thead><tr>
                <th><?= e(__('fields.email', 'Email')) ?></th>
                <th>IP</th>
                <th><?= e(__('admin.result', 'Result')) ?></th>
                <th><?= e(__('admin.reason', 'Reason')) ?></th>
                <th><?= e(__('fields.created', 'Created')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td><?= e($row['email']) ?></td>
                    <td class="text-sm text-muted"><?= e($row['ip']) ?></td>
                    <td><span class="badge badge-<?= (int) $row['success'] === 1 ? 'success' : 'danger' ?>"><?= (int) $row['success'] === 1 ? e(__('admin.login_ok', 'success')) : e(__('admin.login_failed', 'failed')) ?></span></td>
                    <td class="text-sm text-muted"><?= $row['reason'] !== null && $row['reason'] !== '' ? e($row['reason']) : '—' ?></td>
                    <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $row['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        <?php else: ?>
            <thead><tr>
                <th><?= e(__('admin.action', 'Action')) ?></th>
                <th><?= e(__('admin.subject', 'Subject')) ?></th>
                <th><?= e(__('admin.user', 'User')) ?></th>
                <th>IP</th>
                <th><?= e(__('fields.created', 'Created')) ?></th>
                <th><?= e(__('admin.meta', 'Meta')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td class="text-sm"><?= e($row['action']) ?></td>
                    <td class="text-sm text-muted"><?= $row['subject_type'] ? e($row['subject_type'] . ' #' . $row['subject_id']) : '—' ?></td>
                    <td class="tabular"><?= $row['user_id'] !== null ? '#' . (int) $row['user_id'] : '—' ?></td>
                    <td class="text-sm text-muted"><?= e($row['ip'] ?? '—') ?></td>
                    <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $row['created_at'])) ?></td>
                    <td class="text-sm">
                        <?php if ($row['meta'] !== null && $row['meta'] !== '' && $row['meta'] !== '[]'): ?>
                            <details>
                                <summary class="text-muted"><?= e(__('common.details', 'Details')) ?></summary>
                                <pre class="text-xs" style="white-space:pre-wrap;max-height:180px;overflow-y:auto"><?= e(mb_substr((string) $row['meta'], 0, 2000)) ?></pre>
                            </details>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        <?php endif; ?>
    </table>
    <?php if (empty($result['data'])): ?>
        <div class="empty-state"><?= e(__('admin.no_logs', 'No log entries yet.')) ?></div>
    <?php endif; ?>
</div>

<?php View::partial('partials/pagination', [
    'result' => $result,
    'baseUrl' => url('/admin/logs') . '?type=' . urlencode($type),
]); ?>
<?php View::end(); ?>
