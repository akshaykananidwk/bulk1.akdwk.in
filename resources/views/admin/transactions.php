<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('admin.transactions', 'Transactions')) ?></h1>
    <form method="get" action="<?= e(url('/admin/transactions')) ?>" class="flex gap-2 items-center">
        <select class="input" name="status" onchange="this.form.submit()">
            <option value="" <?= $status === '' ? 'selected' : '' ?>><?= e(__('admin.all_statuses', 'All statuses')) ?></option>
            <?php foreach (['pending', 'success', 'failed', 'refunded'] as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline" type="submit"><?= e(__('common.filter', 'Filter')) ?></button>
    </form>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.revenue_total', 'Total revenue')) ?></span>
        <span class="stat-value"><?= e(format_money((float) $totals['success'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.revenue_month', 'This month')) ?></span>
        <span class="stat-value"><?= e(format_money((float) $totals['month'])) ?></span>
    </div>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th>#</th>
            <th><?= e(__('admin.tenant', 'Tenant')) ?></th>
            <th><?= e(__('fields.gateway', 'Gateway')) ?></th>
            <th><?= e(__('admin.gateway_txn', 'Gateway ref')) ?></th>
            <th><?= e(__('fields.type', 'Type')) ?></th>
            <th><?= e(__('fields.amount', 'Amount')) ?></th>
            <th><?= e(__('fields.status', 'Status')) ?></th>
            <th><?= e(__('fields.created', 'Created')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($result['data'] as $row): ?>
            <tr>
                <td class="tabular"><?= (int) $row['id'] ?></td>
                <td><a href="<?= e(url('/admin/tenants/' . (int) $row['tenant_id'])) ?>"><?= e($row['tenant_name']) ?></a></td>
                <td><?= e($row['gateway']) ?></td>
                <td class="text-sm text-muted"><?= $row['gateway_txn_id'] !== null && $row['gateway_txn_id'] !== '' ? e($row['gateway_txn_id']) : '—' ?></td>
                <td><?= e($row['type']) ?></td>
                <td class="tabular"><?= e(format_money((float) $row['amount'], (string) $row['currency'])) ?></td>
                <td>
                    <?php $badge = match ($row['status']) { 'success' => 'success', 'failed' => 'danger', 'refunded' => 'warning', default => 'muted' }; ?>
                    <span class="badge badge-<?= e($badge) ?>"><?= e($row['status']) ?></span>
                </td>
                <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $row['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($result['data'])): ?>
            <tr><td colspan="8" class="text-center text-muted"><?= e(__('admin.no_transactions', 'No transactions found.')) ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php View::partial('partials/pagination', [
    'result' => $result,
    'baseUrl' => url('/admin/transactions') . ($status !== '' ? '?status=' . urlencode($status) : ''),
]); ?>
<?php View::end(); ?>
