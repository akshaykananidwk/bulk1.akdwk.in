<?php
use App\Core\Layout;
use App\Core\View;

Layout::pushScript(url('/assets/vendor/apexcharts.min.js'));
?>
<?php View::start('content'); ?>
<div class="page-header">
    <div>
        <h1><?= e(__('dashboard.title', 'Dashboard')) ?></h1>
        <p class="text-muted text-sm" style="margin:0"><?= e(__('dashboard.subtitle', "Here's what's happening in your workspace")) ?></p>
    </div>
</div>

<?php if (!$connected): ?>
    <div class="alert alert-warning">
        <span>📱</span>
        <div>
            <strong><?= e(__('dashboard.connect_title', 'Connect your WhatsApp number')) ?></strong><br>
            <?= e(__('dashboard.connect_sub', 'Link your WhatsApp Business account to start sending and receiving messages.')) ?>
            <a href="<?= e(url('/tenant/whatsapp')) ?>" class="btn btn-primary btn-sm mt-2"><?= e(__('dashboard.connect_button', 'Connect WhatsApp')) ?></a>
        </div>
    </div>
<?php endif; ?>

<?php if ($trialEndsAt !== null && strtotime((string) $trialEndsAt) > time()): ?>
    <div class="alert alert-info">
        <span>⏳</span>
        <div><?= e(__('dashboard.trial_note', 'Trial ends')) ?> <strong><?= e(\App\Core\DateHelper::display($trialEndsAt, 'd M Y')) ?></strong> — <a href="<?= e(url('/tenant/billing')) ?>"><?= e(__('dashboard.upgrade', 'Upgrade now')) ?></a></div>
    </div>
<?php endif; ?>

<div class="stat-grid">
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('dashboard.stat_contacts', 'Contacts')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['contacts'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('dashboard.stat_messages_today', 'Messages today')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['messages_today'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('dashboard.stat_open', 'Open conversations')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['open_conversations'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('dashboard.stat_campaigns', 'Active campaigns')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['campaigns_running'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('dashboard.stat_cost', 'Message cost (month)')) ?></span>
        <span class="stat-value"><?= e(format_money((float) $stats['cost_month'])) ?></span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><?= e(__('dashboard.volume_title', 'Message volume — last 14 days')) ?></h2>
    </div>
    <div id="volume-chart" style="min-height:280px"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var raw = <?= json_encode($volume, JSON_UNESCAPED_UNICODE) ?>;
    var days = [], inbound = [], outbound = [];
    var byDate = {};
    raw.forEach(function (r) { byDate[r.d] = r; });
    for (var i = 13; i >= 0; i--) {
        var d = new Date(Date.now() - i * 86400000);
        var key = d.toISOString().slice(0, 10);
        days.push(key.slice(5));
        inbound.push(byDate[key] ? Number(byDate[key].inbound) : 0);
        outbound.push(byDate[key] ? Number(byDate[key].outbound) : 0);
    }
    if (window.ApexCharts) {
        new ApexCharts(document.querySelector('#volume-chart'), {
            chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: '<?= e(__('dashboard.inbound', 'Received')) ?>', data: inbound },
                { name: '<?= e(__('dashboard.outbound', 'Sent')) ?>', data: outbound }
            ],
            xaxis: { categories: days, labels: { style: { colors: '#94a3b8' } } },
            colors: ['#3B82F6', '#0F766E'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: .3, opacityTo: .02 } },
            dataLabels: { enabled: false },
            grid: { borderColor: 'rgba(148,163,184,.15)' },
            legend: { labels: { colors: '#94a3b8' } }
        }).render();
    }
});
</script>
<?php View::end(); ?>
