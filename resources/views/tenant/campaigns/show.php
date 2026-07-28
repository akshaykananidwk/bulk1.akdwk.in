<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <div>
        <h1><?= e($campaign['name']) ?></h1>
        <p class="text-muted text-sm" style="margin:0">
            <?= e(__('campaigns.template', 'Template')) ?>: <strong><?= e($template['name'] ?? '—') ?></strong>
            · <?= e(__('campaigns.throttle', 'Messages / minute')) ?>: <?= (int) $campaign['throttle_per_minute'] ?>
            <?php if ($campaign['scheduled_at']): ?> · ⏰ <?= e(\App\Core\DateHelper::display((string) $campaign['scheduled_at'])) ?><?php endif; ?>
        </p>
    </div>
    <div class="flex gap-2">
        <?php if (in_array($campaign['status'], ['draft', 'scheduled'], true)): ?>
            <form method="post" action="<?= e(url('/tenant/campaigns/' . (int) $campaign['id'] . '/launch')) ?>" data-confirm="<?= e(__('campaigns.launch_confirm', 'Launch this campaign now?')) ?>"><?= csrf_field() ?>
                <button class="btn btn-primary" type="submit">🚀 <?= e(__('campaigns.launch', 'Launch')) ?></button>
            </form>
        <?php endif; ?>
        <?php if ($campaign['status'] === 'running'): ?>
            <form method="post" action="<?= e(url('/tenant/campaigns/' . (int) $campaign['id'] . '/pause')) ?>"><?= csrf_field() ?>
                <button class="btn btn-outline" type="submit">⏸ <?= e(__('campaigns.pause', 'Pause')) ?></button>
            </form>
        <?php endif; ?>
        <?php if ($campaign['status'] === 'paused'): ?>
            <form method="post" action="<?= e(url('/tenant/campaigns/' . (int) $campaign['id'] . '/resume')) ?>"><?= csrf_field() ?>
                <button class="btn btn-primary" type="submit">▶ <?= e(__('campaigns.resume', 'Resume')) ?></button>
            </form>
        <?php endif; ?>
        <?php if (in_array($campaign['status'], ['running', 'paused', 'scheduled', 'draft'], true)): ?>
            <form method="post" action="<?= e(url('/tenant/campaigns/' . (int) $campaign['id'] . '/cancel')) ?>" data-confirm="<?= e(__('campaigns.cancel_confirm', 'Cancel this campaign?')) ?>"><?= csrf_field() ?>
                <button class="btn btn-danger" type="submit">✕ <?= e(__('campaigns.cancel', 'Cancel')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="stat-grid" x-data="progressPoll()" x-init="init()">
    <div class="card stat-card"><span class="stat-label"><?= e(__('campaigns.recipients', 'Recipients')) ?></span><span class="stat-value" x-text="stats.total"><?= e(number_format((float) $campaign['total_recipients'])) ?></span></div>
    <div class="card stat-card"><span class="stat-label"><?= e(__('campaigns.sent', 'Sent')) ?></span><span class="stat-value" x-text="stats.sent"><?= e(number_format((float) $campaign['sent_count'])) ?></span></div>
    <div class="card stat-card"><span class="stat-label"><?= e(__('campaigns.delivered', 'Delivered')) ?></span><span class="stat-value" x-text="stats.delivered"><?= e(number_format((float) $campaign['delivered_count'])) ?></span></div>
    <div class="card stat-card"><span class="stat-label"><?= e(__('campaigns.read', 'Read')) ?></span><span class="stat-value" x-text="stats.read"><?= e(number_format((float) $campaign['read_count'])) ?></span></div>
    <div class="card stat-card"><span class="stat-label"><?= e(__('campaigns.failed', 'Failed')) ?></span><span class="stat-value" x-text="stats.failed"><?= e(number_format((float) $campaign['failed_count'])) ?></span></div>
    <div class="card stat-card"><span class="stat-label"><?= e(__('campaigns.cost', 'Cost')) ?></span><span class="stat-value" x-text="'₹' + stats.cost"><?= e(format_money((float) $campaign['total_cost'])) ?></span></div>
</div>

<?php $total = max(1, (int) $campaign['total_recipients']); $done = (int) $campaign['sent_count'] + (int) $campaign['failed_count']; ?>
<div class="card mb-4">
    <div class="flex items-center justify-between mb-2">
        <span class="font-semi"><?= e(__('campaigns.progress', 'Progress')) ?></span>
        <span class="badge badge-<?= $campaign['status'] === 'completed' ? 'success' : 'info' ?>"><?= e($campaign['status']) ?></span>
    </div>
    <div class="progress"><div class="progress-bar <?= $campaign['status'] === 'running' ? 'striped' : '' ?>" id="camp-bar" style="width:<?= (int) round($done / $total * 100) ?>%"></div></div>
</div>

<?php if (!empty($recentFailures)): ?>
    <div class="card">
        <h3 class="card-title"><?= e(__('campaigns.recent_failures', 'Recent failures')) ?></h3>
        <div class="table-wrap" style="border:none">
            <table class="table">
                <thead><tr><th><?= e(__('fields.phone', 'Phone')) ?></th><th><?= e(__('fields.name', 'Name')) ?></th><th><?= e(__('campaigns.error', 'Error')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($recentFailures as $failure): ?>
                    <tr><td class="tabular"><?= e($failure['phone']) ?></td><td><?= e($failure['name'] ?? '—') ?></td><td class="text-sm"><?= e($failure['error'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
function progressPoll() {
    return {
        stats: {
            total: '<?= (int) $campaign['total_recipients'] ?>', sent: '<?= (int) $campaign['sent_count'] ?>',
            delivered: '<?= (int) $campaign['delivered_count'] ?>', read: '<?= (int) $campaign['read_count'] ?>',
            failed: '<?= (int) $campaign['failed_count'] ?>', cost: '<?= (float) $campaign['total_cost'] ?>'
        },
        init() {
            <?php if (in_array($campaign['status'], ['running', 'scheduled'], true)): ?>
            setInterval(() => {
                kwc.fetch('<?= e(url('/tenant/campaigns/' . (int) $campaign['id'] . '/progress')) ?>').then(r => {
                    if (!r.ok) { return; }
                    this.stats = r.data;
                    const total = Math.max(1, r.data.total);
                    const bar = document.getElementById('camp-bar');
                    if (bar) { bar.style.width = Math.round((r.data.sent + r.data.failed) / total * 100) + '%'; }
                    if (r.data.status === 'completed') { window.location.reload(); }
                });
            }, 4000);
            <?php endif; ?>
        }
    };
}
</script>
<?php View::end(); ?>
