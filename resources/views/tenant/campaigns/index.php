<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('nav.campaigns', 'Campaigns')) ?></h1>
    <a class="btn btn-primary" href="<?= e(url('/tenant/campaigns/create')) ?>">＋ <?= e(__('campaigns.new', 'New campaign')) ?></a>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th><?= e(__('fields.name', 'Name')) ?></th>
            <th><?= e(__('fields.status', 'Status')) ?></th>
            <th><?= e(__('campaigns.recipients', 'Recipients')) ?></th>
            <th><?= e(__('campaigns.sent', 'Sent')) ?></th>
            <th><?= e(__('campaigns.read', 'Read')) ?></th>
            <th><?= e(__('campaigns.failed', 'Failed')) ?></th>
            <th><?= e(__('campaigns.cost', 'Cost')) ?></th>
            <th><?= e(__('fields.created', 'Created')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($result['data'] as $campaign): ?>
            <?php $badge = match ($campaign['status']) {
                'running' => 'info', 'completed' => 'success', 'failed', 'cancelled' => 'danger',
                'paused' => 'warning', 'scheduled' => 'primary', default => 'muted',
            }; ?>
            <tr>
                <td><a class="font-semi" href="<?= e(url('/tenant/campaigns/' . (int) $campaign['id'])) ?>"><?= e($campaign['name']) ?></a></td>
                <td><span class="badge badge-<?= e($badge) ?>"><?= e($campaign['status']) ?></span></td>
                <td class="tabular"><?= e(number_format((float) $campaign['total_recipients'])) ?></td>
                <td class="tabular"><?= e(number_format((float) $campaign['sent_count'])) ?></td>
                <td class="tabular"><?= e(number_format((float) $campaign['read_count'])) ?></td>
                <td class="tabular"><?= e(number_format((float) $campaign['failed_count'])) ?></td>
                <td class="tabular"><?= e(format_money((float) $campaign['total_cost'])) ?></td>
                <td class="text-sm text-muted"><?= e(time_ago((string) $campaign['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($result['data'])): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="icon">📣</div><p><?= e(__('campaigns.empty', 'No campaigns yet. Create your first broadcast.')) ?></p></div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php View::partial('partials/pagination', ['result' => $result, 'baseUrl' => url('/tenant/campaigns')]); ?>
<?php View::end(); ?>
