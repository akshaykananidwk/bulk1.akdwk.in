<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<?php $tenantId = (int) $workspace['id']; ?>
<div class="page-header">
    <div>
        <h1><?= e($workspace['name']) ?>
            <?php $badge = match ($workspace['status']) { 'active' => 'success', 'suspended' => 'danger', 'pending' => 'warning', default => 'muted' }; ?>
            <span class="badge badge-<?= e($badge) ?>"><?= e($workspace['status']) ?></span>
        </h1>
        <p class="text-sm text-muted" style="margin:0">
            <?= e($workspace['email']) ?>
            · <?= e(__('fields.created', 'Created')) ?> <?= e(\App\Core\DateHelper::display((string) $workspace['created_at'])) ?>
            <?php if (!empty($workspace['trial_ends_at'])): ?>
                · <?= e(__('admin.trial_ends', 'Trial ends')) ?> <?= e(\App\Core\DateHelper::display((string) $workspace['trial_ends_at'])) ?>
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(url('/admin/tenants')) ?>">‹ <?= e(__('common.back', 'Back')) ?></a>
</div>

<div class="stat-grid">
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.stat_contacts', 'Contacts')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['contacts'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.stat_messages_month', 'Messages this month')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['messages_month'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.stat_campaigns', 'Campaigns')) ?></span>
        <span class="stat-value"><?= e(number_format((float) $stats['campaigns'])) ?></span>
    </div>
    <div class="card stat-card">
        <span class="stat-label"><?= e(__('admin.wallet_balance', 'Wallet balance')) ?></span>
        <span class="stat-value"><?= e(format_money((float) ($wallet['balance'] ?? 0))) ?></span>
        <span class="stat-delta"><?= e(__('fields.plan', 'Plan')) ?>: <?= $plan ? e($plan['name']) : '—' ?></span>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.actions', 'Actions')) ?></h2></div>

        <div class="flex gap-2 items-center mb-2">
            <?php if ($workspace['status'] === 'suspended'): ?>
                <form method="post" action="<?= e(url('/admin/tenants/' . $tenantId . '/activate')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary btn-sm" type="submit"><?= e(__('admin.activate', 'Activate')) ?></button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= e(url('/admin/tenants/' . $tenantId . '/suspend')) ?>" data-confirm="<?= e(__('admin.suspend_confirm', 'Suspend this tenant? Their users will be locked out.')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-danger btn-sm" type="submit"><?= e(__('admin.suspend', 'Suspend')) ?></button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/admin/tenants/' . $tenantId . '/impersonate')) ?>" data-confirm="<?= e(__('admin.impersonate_confirm', 'Log in as this tenant\'s owner? The action is audited.')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline btn-sm" type="submit"><?= e(__('admin.impersonate', 'Impersonate')) ?></button>
            </form>
        </div>

        <form method="post" action="<?= e(url('/admin/tenants/' . $tenantId . '/extend-trial')) ?>" class="flex gap-2 items-center mb-2">
            <?= csrf_field() ?>
            <div class="form-group" style="margin:0">
                <label class="form-label"><?= e(__('admin.extend_trial', 'Extend trial (days)')) ?></label>
                <input class="input" type="number" name="days" value="7" min="1" max="365" required>
            </div>
            <button class="btn btn-outline btn-sm" type="submit"><?= e(__('admin.extend', 'Extend')) ?></button>
        </form>

        <form method="post" action="<?= e(url('/admin/tenants/' . $tenantId . '/wallet')) ?>" class="flex gap-2 items-center mb-2">
            <?= csrf_field() ?>
            <div class="form-group" style="margin:0">
                <label class="form-label"><?= e(__('admin.wallet_amount', 'Wallet ± amount')) ?></label>
                <input class="input" type="number" step="0.01" name="amount" placeholder="100 / -100" required>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label"><?= e(__('admin.wallet_reason', 'Reason')) ?></label>
                <input class="input" name="reason" placeholder="<?= e(__('admin.wallet_reason_ph', 'Admin adjustment')) ?>">
            </div>
            <button class="btn btn-outline btn-sm" type="submit"><?= e(__('common.apply', 'Apply')) ?></button>
        </form>

        <form method="post" action="<?= e(url('/admin/tenants/' . $tenantId . '/plan')) ?>" class="flex gap-2 items-center">
            <?= csrf_field() ?>
            <div class="form-group" style="margin:0">
                <label class="form-label"><?= e(__('admin.change_plan', 'Change plan')) ?></label>
                <select class="input" name="plan_id" required>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($workspace['plan_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> — <?= e(format_money((float) $p['price_monthly'])) ?>/mo
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-outline btn-sm" type="submit"><?= e(__('common.save', 'Save')) ?></button>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.users', 'Users')) ?></h2></div>
        <div class="table-wrap" style="border:none">
            <table class="table">
                <thead><tr>
                    <th><?= e(__('fields.name', 'Name')) ?></th>
                    <th><?= e(__('fields.status', 'Status')) ?></th>
                    <th><?= e(__('team.last_login', 'Last login')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <?= e($user['name']) ?>
                            <div class="text-xs text-muted"><?= e($user['email']) ?></div>
                        </td>
                        <td><span class="badge badge-<?= $user['status'] === 'active' ? 'success' : 'muted' ?>"><?= e($user['status']) ?></span></td>
                        <td class="text-sm text-muted"><?= $user['last_login_at'] ? e(time_ago((string) $user['last_login_at'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                    <tr><td colspan="3" class="text-center text-muted"><?= e(__('admin.no_users', 'This tenant has no users.')) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h2 class="card-title"><?= e(__('admin.numbers', 'WhatsApp numbers')) ?></h2></div>
    <div class="table-wrap" style="border:none">
        <table class="table">
            <thead><tr>
                <th><?= e(__('fields.number', 'Number')) ?></th>
                <th><?= e(__('admin.verified_name', 'Verified name')) ?></th>
                <th><?= e(__('admin.quality', 'Quality')) ?></th>
                <th><?= e(__('fields.status', 'Status')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($numbers as $number): ?>
                <tr>
                    <td class="tabular"><?= e($number['display_phone_number']) ?></td>
                    <td><?= $number['verified_name'] !== null && $number['verified_name'] !== '' ? e($number['verified_name']) : '—' ?></td>
                    <td><?= $number['quality_rating'] !== null && $number['quality_rating'] !== '' ? e($number['quality_rating']) : '—' ?></td>
                    <td><span class="badge badge-<?= $number['status'] === 'active' ? 'success' : ($number['status'] === 'banned' ? 'danger' : 'muted') ?>"><?= e($number['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($numbers)): ?>
                <tr><td colspan="4" class="text-center text-muted"><?= e(__('admin.no_numbers', 'No numbers connected.')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header"><h2 class="card-title"><?= e(__('admin.recent_transactions', 'Recent transactions')) ?></h2></div>
    <div class="table-wrap" style="border:none">
        <table class="table">
            <thead><tr>
                <th>#</th>
                <th><?= e(__('fields.gateway', 'Gateway')) ?></th>
                <th><?= e(__('fields.type', 'Type')) ?></th>
                <th><?= e(__('fields.amount', 'Amount')) ?></th>
                <th><?= e(__('fields.status', 'Status')) ?></th>
                <th><?= e(__('fields.created', 'Created')) ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($transactions as $txn): ?>
                <tr>
                    <td class="tabular"><?= (int) $txn['id'] ?></td>
                    <td><?= e($txn['gateway']) ?></td>
                    <td><?= e($txn['type']) ?></td>
                    <td class="tabular"><?= e(format_money((float) $txn['amount'], (string) $txn['currency'])) ?></td>
                    <td>
                        <?php $tb = match ($txn['status']) { 'success' => 'success', 'failed' => 'danger', 'refunded' => 'warning', default => 'muted' }; ?>
                        <span class="badge badge-<?= e($tb) ?>"><?= e($txn['status']) ?></span>
                    </td>
                    <td class="text-sm text-muted"><?= e(\App\Core\DateHelper::display((string) $txn['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="6" class="text-center text-muted"><?= e(__('admin.no_transactions', 'No transactions yet.')) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php View::end(); ?>
