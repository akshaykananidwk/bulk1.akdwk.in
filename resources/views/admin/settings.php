<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('admin.settings', 'Global Settings')) ?></h1>
</div>

<form method="post" action="<?= e(url('/admin/settings')) ?>">
    <?= csrf_field() ?>

    <div class="card mb-2">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.settings_branding', 'Branding')) ?></h2></div>
        <div class="grid-2">
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.app_name', 'App name')) ?></label>
                <input class="input" name="app_name" value="<?= e($values['app_name']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.app_tagline', 'Tagline')) ?></label>
                <input class="input" name="app_tagline" value="<?= e($values['app_tagline']) ?>">
            </div>
        </div>
        <div class="grid-3">
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.default_language', 'Default language')) ?></label>
                <select class="input" name="default_language">
                    <?php foreach (['en' => 'English', 'gu' => 'ગુજરાતી (Gujarati)', 'hi' => 'हिन्दी (Hindi)'] as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $values['default_language'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.default_currency', 'Default currency')) ?></label>
                <select class="input" name="default_currency">
                    <?php foreach (['INR', 'USD', 'EUR', 'GBP', 'AED'] as $currency): ?>
                        <option value="<?= e($currency) ?>" <?= $values['default_currency'] === $currency ? 'selected' : '' ?>><?= e($currency) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.date_format', 'Date format')) ?></label>
                <input class="input" name="date_format" value="<?= e($values['date_format']) ?>" placeholder="d M Y">
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.settings_registration', 'Registration')) ?></h2></div>
        <div class="grid-2">
            <div class="form-group">
                <label class="checkbox-row">
                    <input type="hidden" name="registration_enabled" value="0">
                    <input type="checkbox" name="registration_enabled" value="1" <?= $values['registration_enabled'] === '1' ? 'checked' : '' ?>>
                    <?= e(__('admin.registration_enabled', 'Allow public sign-ups')) ?>
                </label>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.default_plan_slug', 'Default plan slug for new sign-ups')) ?></label>
                <input class="input" name="default_plan_slug" value="<?= e($values['default_plan_slug']) ?>" placeholder="free">
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.settings_mail', 'Mail')) ?></h2></div>
        <?php $mailLastError = (string) setting('mail_last_error', ''); ?>
        <?php if ($mailLastError !== ''): ?>
            <div class="alert alert-danger">
                <span>📧</span>
                <div>
                    <strong><?= e(__('admin.mail_failing', 'Emails are failing!')) ?></strong>
                    <?= e(__('admin.mail_last_error', 'Last error')) ?> (<?= e(\App\Core\DateHelper::display((string) setting('mail_last_error_at', ''))) ?>):<br>
                    <code style="word-break:break-word"><?= e($mailLastError) ?></code>
                </div>
            </div>
        <?php elseif ((string) setting('mail_last_success_at', '') !== ''): ?>
            <div class="text-sm text-muted mb-2">✅ <?= e(__('admin.mail_last_ok', 'Last email sent successfully')) ?>: <?= e(\App\Core\DateHelper::display((string) setting('mail_last_success_at', ''))) ?></div>
        <?php endif; ?>
        <div class="grid-3">
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.mail_driver', 'Driver')) ?></label>
                <select class="input" name="mail_driver">
                    <option value="mail" <?= $values['mail_driver'] === 'mail' ? 'selected' : '' ?>>PHP mail()</option>
                    <option value="smtp" <?= $values['mail_driver'] === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.mail_host', 'SMTP host')) ?></label>
                <input class="input" name="mail_host" value="<?= e($values['mail_host']) ?>" placeholder="smtp.example.com">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.mail_port', 'Port')) ?></label>
                <input class="input" type="number" name="mail_port" value="<?= e($values['mail_port']) ?>" placeholder="587">
            </div>
        </div>
        <div class="grid-3">
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.mail_encryption', 'Encryption')) ?></label>
                <select class="input" name="mail_encryption">
                    <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => __('common.none', 'None')] as $enc => $label): ?>
                        <option value="<?= e($enc) ?>" <?= $values['mail_encryption'] === $enc ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.mail_username', 'Username')) ?></label>
                <input class="input" name="mail_username" value="<?= e($values['mail_username']) ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.mail_password', 'Password')) ?></label>
                <input class="input" type="password" name="mail_password" value="" placeholder="<?= e($values['mail_password'] !== '' ? $values['mail_password'] : __('admin.mail_password_ph', 'not set')) ?>" autocomplete="new-password">
                <div class="form-hint"><?= e(__('admin.mail_password_hint', 'Leave blank to keep the current password.')) ?></div>
            </div>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.mail_from_email', 'From email')) ?></label>
                <input class="input" type="email" name="mail_from_email" value="<?= e($values['mail_from_email']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.mail_from_name', 'From name')) ?></label>
                <input class="input" name="mail_from_name" value="<?= e($values['mail_from_name']) ?>">
            </div>
        </div>
        <label class="checkbox-row">
            <input type="checkbox" name="send_test_mail" value="1">
            <?= e(__('admin.send_test_mail', 'Send a test email to the alert address after saving')) ?>
        </label>
    </div>

    <div class="card mb-2">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.settings_alerts', 'Alerts')) ?></h2></div>
        <div class="grid-2">
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.alert_email', 'Alert email')) ?></label>
                <input class="input" type="email" name="alert_email" value="<?= e($values['alert_email']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.queue_backlog_alert', 'Queue backlog alert threshold')) ?></label>
                <input class="input" type="number" name="queue_backlog_alert" value="<?= e($values['queue_backlog_alert']) ?>" min="0" placeholder="1000">
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.settings_security', 'Security')) ?></h2></div>
        <div class="form-group">
            <label class="form-label"><?= e(__('admin.admin_ip_allowlist', 'Admin IP allowlist')) ?></label>
            <textarea class="input" name="admin_ip_allowlist" rows="2" placeholder="1.2.3.4, 5.6.7.0/24"><?= e($values['admin_ip_allowlist']) ?></textarea>
            <div class="form-hint"><?= e(__('admin.admin_ip_hint', 'Comma-separated IPs or CIDR ranges. Leave blank to allow all.')) ?></div>
        </div>
        <div class="form-group">
            <label class="form-label"><?= e(__('admin.captcha_provider', 'Captcha on login/register')) ?></label>
            <select class="input" name="captcha_provider">
                <option value="math" <?= $values['captcha_provider'] === 'math' ? 'selected' : '' ?>><?= e(__('admin.captcha_math', 'Math question')) ?></option>
                <option value="none" <?= $values['captcha_provider'] === 'none' ? 'selected' : '' ?>><?= e(__('common.none', 'None')) ?></option>
            </select>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card-header"><h2 class="card-title"><?= e(__('admin.settings_system', 'System')) ?></h2></div>
        <div class="grid-2">
            <div class="form-group">
                <label class="form-label"><?= e(__('admin.worker_mode', 'Queue worker mode')) ?></label>
                <select class="input" name="worker_mode">
                    <option value="cron" <?= $values['worker_mode'] === 'cron' ? 'selected' : '' ?>><?= e(__('admin.worker_cron', 'Cron (shared hosting)')) ?></option>
                    <option value="pm2" <?= $values['worker_mode'] === 'pm2' ? 'selected' : '' ?>><?= e(__('admin.worker_pm2', 'PM2 / long-running worker')) ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="checkbox-row">
                    <input type="hidden" name="backup_daily" value="0">
                    <input type="checkbox" name="backup_daily" value="1" <?= $values['backup_daily'] === '1' ? 'checked' : '' ?>>
                    <?= e(__('admin.backup_daily', 'Take a daily database backup')) ?>
                </label>
            </div>
        </div>
    </div>

    <button class="btn btn-primary" type="submit"><?= e(__('common.save', 'Save settings')) ?></button>
</form>
<?php View::end(); ?>
