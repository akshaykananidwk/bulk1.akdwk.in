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

            <h3 class="card-title mt-4">🛡️ <?= e(__('settings.antiblock', 'Anti-block (human-like sending)')) ?></h3>
            <div class="form-hint mb-2"><?= e(__('settings.antiblock_hint', 'Makes automated sending behave like a person, which strongly reduces the chance of WhatsApp blocking your number.')) ?></div>
            <div class="form-group">
                <label class="checkbox-row">
                    <input type="hidden" name="humanize_typing" value="0">
                    <input type="checkbox" name="humanize_typing" value="1" <?= $settings['humanize_typing'] === '1' ? 'checked' : '' ?>>
                    <?= e(__('settings.humanize_typing', 'Show "typing…" and wait before automated replies (bots, flows, auto-replies)')) ?>
                </label>
            </div>
            <div class="form-group"><label class="form-label"><?= e(__('settings.typing_seconds', 'Typing time before the reply goes out (seconds, 3-25)')) ?></label>
                <input class="input" type="number" name="humanize_typing_seconds" value="<?= e($settings['humanize_typing_seconds']) ?>" min="3" max="25"></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label"><?= e(__('settings.gap_min', 'Campaign gap between messages — min seconds')) ?></label>
                    <input class="input" type="number" name="campaign_gap_min" value="<?= e($settings['campaign_gap_min']) ?>" min="0" max="120"></div>
                <div class="form-group"><label class="form-label"><?= e(__('settings.gap_max', 'max seconds (0 = no gap)')) ?></label>
                    <input class="input" type="number" name="campaign_gap_max" value="<?= e($settings['campaign_gap_max']) ?>" min="0" max="300"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label"><?= e(__('settings.daily_cap', 'Campaign messages per day (0 = unlimited)')) ?></label>
                    <input class="input" type="number" name="campaign_daily_cap" value="<?= e($settings['campaign_daily_cap']) ?>" min="0" max="100000"></div>
                <div class="form-group"><label class="form-label"><?= e(__('settings.quiet_hours', 'Quiet hours (no campaign sends)')) ?></label>
                    <input class="input" name="campaign_quiet_hours" value="<?= e($settings['campaign_quiet_hours']) ?>" placeholder="21:00-09:00">
                    <div class="form-hint"><?= e(__('settings.quiet_hint', 'Format HH:MM-HH:MM, may cross midnight. Blank = off.')) ?></div></div>
            </div>
            <div class="form-group">
                <label class="checkbox-row">
                    <input type="hidden" name="campaign_warmup" value="0">
                    <input type="checkbox" name="campaign_warmup" value="1" <?= $settings['campaign_warmup'] === '1' ? 'checked' : '' ?>>
                    <?= e(__('settings.warmup', 'Warm-up: auto-limit new WhatsApp numbers (250/day first week → 500 → 1000 → 2000, then unlimited)')) ?>
                </label>
            </div>
        </div>
    </div>
    <div class="mt-4"><button class="btn btn-primary btn-lg" type="submit"><?= e(__('common.save', 'Save')) ?></button></div>
</form>
<?php View::end(); ?>
