<?php
use App\Core\Layout;
use App\Core\View;

Layout::pushScript(url('/assets/vendor/apexcharts.min.js'));
?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('admin.dashboard_title', 'Platform overview')) ?></h1>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <span class="stat-label">MRR</span>
        <span class="stat-value"><?= e(format_money((float) $stats['mrr'])) ?></span>
        <span class="stat-delta">ARR <?= e(format_money((float) $stats['arr'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.stat_tenants', 'Tenants')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['tenants_active'])) ?></span>
        <span class="stat-delta"><?= e(number_format((float) $stats['tenants_total'])) ?> <?= e(__('admin.stat_total', 'total')) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.stat_revenue', 'Revenue this month')) ?></span>
        <span class="stat-value"><?= e(format_money((float) $stats['revenue_month'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.stat_messages', 'Messages today')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['messages_today'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.stat_queue', 'Queue backlog')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['queue_backlog'])) ?></span>
        <span class="stat-delta <?= $stats['failed_jobs'] > 0 ? 'down' : '' ?>"><?= e(number_format((float) $stats['failed_jobs'])) ?> <?= e(__('admin.stat_failed', 'failed')) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.stat_tickets', 'Open tickets')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['open_tickets'])) ?></span>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.revenue_chart', 'Revenue — last 30 days')) ?></h2></div>
        <div id="revenue-chart" style="min-height:260px"></div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= e(__('admin.recent_tenants', 'Newest tenants')) ?></h2>
            <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/tenants')) ?>"><?= e(__('common.view_all', 'View all')) ?></a>
        </div>
        <div class="table-wrap" style="border:none">
            <table class="table">
                <thead><tr><th><?= e(__('fields.name', 'Name')) ?></th><th><?= e(__('fields.status', 'Status')) ?></th><th><?= e(__('fields.created', 'Created')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($recentTenants as $row): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('/admin/tenants/' . (int) $row['id'])) ?>"><?= e($row['name']) ?></a>
                            <div class="text-xs text-muted"><?= e($row['email']) ?></div>
                        </td>
                        <td><span class="badge badge-<?= $row['status'] === 'active' ? 'success' : 'muted' ?>"><?= e($row['status']) ?></span></td>
                        <td class="text-sm text-muted"><?= e(time_ago((string) $row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentTenants)): ?>
                    <tr><td colspan="3" class="text-center text-muted"><?= e(__('common.no_data', 'No data yet')) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var raw = <?= json_encode($revenue, JSON_UNESCAPED_UNICODE) ?>;
    var byDate = {};
    raw.forEach(function (r) { byDate[r.d] = Number(r.total); });
    var days = [], totals = [];
    for (var i = 29; i >= 0; i--) {
        var key = new Date(Date.now() - i * 86400000).toISOString().slice(0, 10);
        days.push(key.slice(5));
        totals.push(byDate[key] || 0);
    }
    if (window.ApexCharts) {
        new ApexCharts(document.querySelector('#revenue-chart'), {
            chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Revenue', data: totals }],
            xaxis: { categories: days, labels: { show: false } },
            colors: ['#F59E0B'],
            dataLabels: { enabled: false },
            grid: { borderColor: 'rgba(148,163,184,.15)' }
        }).render();
    }
});
</script>
<?php View::end(); ?>
