<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header"><h1><?= e(__('nav.settings', 'Settings')) ?></h1></div>

<form method="post" action="<?= e(url('/tenant/settings')) ?>">
    <?= csrf_field() ?>
    <div class="grid-2">
        <div class="card">
            <h3 class="card-title"><?= e(__('settings.workspace', 'Workspace')) ?></h3>
            <div class="form-group"><label class="form-label"><?= e(__('settings.name', 'Workspace name')) ?></label>
                <input class="input" name="name" value="<?= e($workspace['name'] ?? '') ?>" required></div>
            <div class="form-group"><label class="form-label"><?= e(__('settings.timezone', 'Timezone')) ?></label>
                <select class="input" name="timezone">
                    <?php foreach (['Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'America/New_York', 'UTC'] as $tz): ?>
                        <option value="<?= e($tz) ?>" <?= ($workspace['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <h3 class="card-title mt-4"><?= e(__('settings.billing_info', 'Billing details (GST invoices)')) ?></h3>
            <div class="form-group"><label class="form-label">GSTIN</label>
                <input class="input" name="gstin" value="<?= e($workspace['gstin'] ?? '') ?>" placeholder="24XXXXX0000X1Z5"></div>
            <div class="form-group"><label class="form-label"><?= e(__('settings.billing_name', 'Billing name')) ?></label>
                <input class="input" name="billing_name" value="<?= e($workspace['billing_name'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label"><?= e(__('settings.billing_address', 'Billing address')) ?></label>
                <textarea class="input" name="billing_address" rows="3"><?= e($workspace['billing_address'] ?? '') ?></textarea></div>
        </div>

        <div class="card">
            <h3 class="card-title"><?= e(__('settings.automation', 'Inbox automation')) ?></h3>
            <div class="form-group"><label class="form-label"><?= e(__('settings.greeting', 'Greeting message (first contact)')) ?></label>
                <textarea class="input" name="greeting_message" rows="2"><?= e($settings['greeting_message']) ?></textarea></div>
            <div class="form-group"><label class="form-label"><?= e(__('settings.away', 'Away message (outside business hours)')) ?></label>
                <textarea class="input" name="away_message" rows="2"><?= e($settings['away_message']) ?></textarea></div>
            <div class="form-group"><label class="form-label"><?= e(__('settings.hours', 'Business hours')) ?></label>
                <input class="input" name="business_hours" value="<?= e($settings['business_hours']) ?>" placeholder="Mon-Sat 09:00-19:00">
                <div class="form-hint"><?= e(__('settings.hours_hint', 'Free text shown to flows/AI; leave empty for 24×7.')) ?></div></div>
            <div class="form-group"><label class="form-label"><?= e(__('settings.auto_close', 'Auto-close idle chats after (hours, 0 = never)')) ?></label>
                <input class="input" type="number" name="auto_close_hours" value="<?= e($settings['auto_close_hours']) ?>" min="0" max="720"></div>

            <h3 class="card-title mt-4"><?= e(__('settings.frequency', 'Marketing frequency cap')) ?></h3>
            <div class="grid-2">
                <div class="form-group"><label class="form-label"><?= e(__('settings.cap_count', 'Max marketing msgs (0 = off)')) ?></label>
                    <input class="input" type="number" name="frequency_cap_count" value="<?= e($settings['frequency_cap_count']) ?>" min="0" max="100"></div>
                <div class="form-group"><label class="form-label"><?= e(__('settings.cap_days', 'per N days')) ?></label>
                    <input class="input" type="number" name="frequency_cap_days" value="<?= e($settings['frequency_cap_days']) ?>" min="1" max="90"></div>
            </div>
        </div>
    </div>
    <div class="mt-4"><button class="btn btn-primary btn-lg" type="submit"><?= e(__('common.save', 'Save')) ?></button></div>
</form>
<?php View::end(); ?>
