<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header"><h1><?= e(__('nav.billing', 'Billing')) ?></h1></div>

<div class="grid-2">
    <div class="card">
        <h3 class="card-title"><?= e(__('billing.current_plan', 'Current plan')) ?></h3>
        <?php if ($plan !== null): ?>
            <div class="flex items-center justify-between">
                <div>
                    <div class="stat-value"><?= e($plan['name']) ?></div>
                    <div class="text-muted text-sm"><?= e(format_money((float) $plan['price_monthly'])) ?>/<?= e(__('billing.month', 'month')) ?></div>
                </div>
                <?php
                $now = date('Y-m-d H:i:s');
                $trialActive = !empty($workspace['trial_ends_at']) && $workspace['trial_ends_at'] > $now;
                $subActive = !empty($workspace['subscription_ends_at']) && $workspace['subscription_ends_at'] > $now;
                ?>
                <span class="badge badge-<?= ($trialActive || $subActive) ? 'success' : 'danger' ?>">
                    <?= $subActive ? e(__('billing.active', 'Active')) : ($trialActive ? e(__('billing.trial', 'Trial')) : e(__('billing.expired_badge', 'Expired'))) ?>
                </span>
            </div>
            <?php if ($trialActive): ?>
                <p class="text-sm text-muted mt-2"><?= e(__('billing.trial_until', 'Trial until')) ?> <?= e(\App\Core\DateHelper::display((string) $workspace['trial_ends_at'], 'd M Y')) ?></p>
            <?php elseif ($subActive): ?>
                <p class="text-sm text-muted mt-2"><?= e(__('billing.renews', 'Renews')) ?> <?= e(\App\Core\DateHelper::display((string) $workspace['subscription_ends_at'], 'd M Y')) ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted"><?= e(__('billing.no_plan', 'No plan assigned — contact support.')) ?></p>
        <?php endif; ?>

        <h3 class="card-title mt-4"><?= e(__('billing.usage', 'Usage')) ?></h3>
        <?php foreach ($usage as $item): ?>
            <?php $percent = $item['limit'] !== null && $item['limit'] > 0 ? min(100, (int) round($item['used'] / $item['limit'] * 100)) : 0; ?>
            <div class="mb-2">
                <div class="flex justify-between text-sm">
                    <span><?= e(ucwords(str_replace('_', ' ', (string) $item['feature']))) ?></span>
                    <span class="tabular"><?= e(number_format((float) $item['used'])) ?> / <?= $item['limit'] === null ? '∞' : e(number_format((float) $item['limit'])) ?></span>
                </div>
                <?php if ($item['limit'] !== null): ?>
                    <div class="progress" style="height:6px"><div class="progress-bar" style="width:<?= $percent ?>%; <?= $percent >= 100 ? 'background:var(--danger)' : ($percent >= 80 ? 'background:var(--warning)' : '') ?>"></div></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <h3 class="card-title mt-4"><?= e(__('billing.wallet', 'Wallet')) ?></h3>
        <div class="stat-value"><?= e(format_money((float) ($wallet['balance'] ?? 0))) ?></div>
        <p class="text-sm text-muted"><?= e(__('billing.wallet_note', 'Wallet auto-pays per-message costs on pay-as-you-go plans. Contact support to recharge until online payments are enabled by your admin.')) ?></p>
    </div>

    <div class="card">
        <h3 class="card-title"><?= e(__('billing.plans', 'Available plans')) ?></h3>
        <?php foreach ($plans as $availablePlan): ?>
            <div class="flex items-center justify-between mb-2" style="padding:.6rem;border:1px solid var(--border);border-radius:10px; <?= $plan !== null && (int) $plan['id'] === (int) $availablePlan['id'] ? 'border-color:var(--primary)' : '' ?>">
                <div>
                    <strong><?= e($availablePlan['name']) ?></strong>
                    <?php if ($availablePlan['is_featured']): ?><span class="badge badge-warning">★</span><?php endif; ?>
                    <div class="text-sm text-muted"><?= e($availablePlan['description'] ?? '') ?></div>
                </div>
                <div class="text-right">
                    <div class="font-bold tabular"><?= e(format_money((float) $availablePlan['price_monthly'])) ?><span class="text-xs text-muted">/mo</span></div>
                    <?php if ($plan !== null && (int) $plan['id'] === (int) $availablePlan['id']): ?>
                        <span class="badge badge-primary"><?= e(__('billing.current', 'Current')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <p class="text-sm text-muted"><?= e(__('billing.upgrade_note', 'To upgrade or renew, contact your platform administrator — online payment checkout activates once a payment gateway is configured in the admin panel.')) ?></p>

        <h3 class="card-title mt-4"><?= e(__('billing.invoices', 'Invoices')) ?></h3>
        <div class="table-wrap" style="border:none">
            <table class="table">
                <thead><tr><th>#</th><th><?= e(__('billing.amount', 'Amount')) ?></th><th><?= e(__('fields.status', 'Status')) ?></th><th><?= e(__('billing.date', 'Date')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?= e($invoice['invoice_number']) ?></td>
                        <td class="tabular"><?= e(format_money((float) $invoice['total'], (string) $invoice['currency'])) ?></td>
                        <td><span class="badge badge-<?= $invoice['status'] === 'paid' ? 'success' : 'warning' ?>"><?= e($invoice['status']) ?></span></td>
                        <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $invoice['created_at'], 'd M Y')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="4" class="text-center text-muted"><?= e(__('common.no_data', 'No data yet')) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php View::end(); ?>
