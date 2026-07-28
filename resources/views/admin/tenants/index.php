<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('admin.tenants', 'Tenants')) ?></h1>
    <form method="get" action="<?= e(url('/admin/tenants')) ?>" class="flex gap-2 items-center">
        <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('admin.search_tenants', 'Search name or email…')) ?>">
        <button class="btn btn-outline" type="submit"><?= e(__('common.search', 'Search')) ?></button>
    </form>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th><?= e(__('fields.name', 'Name')) ?></th>
            <th><?= e(__('fields.plan', 'Plan')) ?></th>
            <th><?= e(__('fields.status', 'Status')) ?></th>
            <th><?= e(__('admin.trial_ends', 'Trial ends')) ?></th>
            <th><?= e(__('fields.created', 'Created')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($result['data'] as $row): ?>
            <tr>
                <td>
                    <a href="<?= e(url('/admin/tenants/' . (int) $row['id'])) ?>"><?= e($row['name']) ?></a>
                    <div class="text-xs text-muted"><?= e($row['email']) ?></div>
                </td>
                <td><?= $row['plan_name'] !== null && $row['plan_name'] !== '' ? e($row['plan_name']) : '—' ?></td>
                <td>
                    <?php $badge = match ($row['status']) { 'active' => 'success', 'suspended' => 'danger', 'pending' => 'warning', default => 'muted' }; ?>
                    <span class="badge badge-<?= e($badge) ?>"><?= e($row['status']) ?></span>
                </td>
                <td class="text-sm text-muted"><?= $row['trial_ends_at'] ? e(\App\Core\DateHelper::display((string) $row['trial_ends_at'])) : '—' ?></td>
                <td class="text-sm text-muted"><?= e(time_ago((string) $row['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($result['data'])): ?>
            <tr><td colspan="5" class="text-center text-muted"><?= e(__('admin.no_tenants', 'No tenants found.')) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php View::partial('partials/pagination', [
    'result' => $result,
    'baseUrl' => url('/admin/tenants') . ($search !== '' ? '?q=' . urlencode($search) : ''),
]); ?>
<?php View::end(); ?>
