<?php use App\Core\Layout; use App\Core\View; Layout::title(__('nav.profile', 'My profile')); ?>
<?php View::start('content'); ?>
<div class="page-header"><h1><?= e(__('nav.profile', 'My profile')) ?></h1></div>

<div class="grid-2">
    <div class="card">
        <h3 class="card-title"><?= e(__('profile.details', 'Details')) ?></h3>
        <form method="post" action="<?= e(url('/profile')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="form-group"><label class="form-label"><?= e(__('fields.name', 'Name')) ?></label>
                <input class="input" name="name" value="<?= e($profile['name'] ?? '') ?>" required></div>
            <div class="form-group"><label class="form-label"><?= e(__('fields.email', 'Email')) ?></label>
                <input class="input" value="<?= e($profile['email'] ?? '') ?>" disabled></div>
            <div class="form-group"><label class="form-label"><?= e(__('fields.phone', 'Phone')) ?></label>
                <input class="input" name="phone" value="<?= e($profile['phone'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label"><?= e(__('profile.timezone', 'Timezone')) ?></label>
                <select class="input" name="timezone">
                    <option value=""><?= e(__('profile.tz_default', 'Workspace default')) ?></option>
                    <?php foreach (['Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'America/New_York', 'UTC'] as $tz): ?>
                        <option value="<?= e($tz) ?>" <?= ($profile['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label class="form-label"><?= e(__('profile.avatar', 'Avatar')) ?></label>
                <input class="input" type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp"></div>
            <button class="btn btn-primary" type="submit"><?= e(__('common.save', 'Save')) ?></button>
        </form>

        <h3 class="card-title mt-4"><?= e(__('profile.language', 'Language')) ?></h3>
        <div class="flex gap-2">
            <?php foreach ($languages as $code => $label): ?>
                <form method="post" action="<?= e(url('/profile/locale')) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="locale" value="<?= e($code) ?>">
                    <button class="btn btn-outline btn-sm" type="submit"><?= e($label) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?= e(__('profile.security', 'Security')) ?></h3>
        <form method="post" action="<?= e(url('/profile/password')) ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label class="form-label"><?= e(__('fields.current_password', 'Current password')) ?></label>
                <input class="input" type="password" name="current_password" required autocomplete="current-password"></div>
            <div class="form-group"><label class="form-label"><?= e(__('profile.new_password', 'New password')) ?></label>
                <input class="input" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
            <div class="form-group"><label class="form-label"><?= e(__('fields.password_confirmation', 'Confirm password')) ?></label>
                <input class="input" type="password" name="password_confirmation" required></div>
            <button class="btn btn-primary" type="submit"><?= e(__('profile.change_password', 'Change password')) ?></button>
        </form>

        <h3 class="card-title mt-4"><?= e(__('profile.2fa', 'Two-factor authentication')) ?></h3>
        <div x-data="{ setup: null, code: '', backupCodes: null }">
            <?php if (empty($profile['two_factor_secret'])): ?>
                <p class="text-sm text-muted"><?= e(__('profile.2fa_off', 'Protect your account with an authenticator app (TOTP).')) ?></p>
                <button class="btn btn-outline" x-show="!setup" x-on:click="
                    kwc.fetch('<?= e(url('/profile/2fa/enable')) ?>', { method: 'POST', json: {} })
                        .then(r => { if (r.ok) { setup = r.data.data; } })">
                    🔐 <?= e(__('profile.2fa_enable', 'Enable 2FA')) ?>
                </button>
                <div x-show="setup" x-cloak>
                    <p class="text-sm"><?= e(__('profile.2fa_scan', 'Add this secret to Google Authenticator / Authy:')) ?></p>
                    <div class="input-group"><input class="input" readonly :value="setup ? setup.secret : ''" style="font-family:monospace">
                        <button class="btn btn-outline" type="button" x-on:click="navigator.clipboard.writeText(setup.secret)">📋</button></div>
                    <div class="form-group mt-2"><label class="form-label"><?= e(__('profile.2fa_confirm_code', 'Enter the 6-digit code to confirm')) ?></label>
                        <input class="input" x-model="code" inputmode="numeric" maxlength="6"></div>
                    <button class="btn btn-primary" x-on:click="
                        kwc.fetch('<?= e(url('/profile/2fa/confirm')) ?>', { method: 'POST', json: { code: code } })
                            .then(r => {
                                if (r.ok) { backupCodes = r.data.data.backup_codes; setup = null; kwc.toast(r.data.message, 'success'); }
                                else { kwc.toast(r.data.message || 'Invalid code', 'danger'); }
                            })"><?= e(__('profile.2fa_confirm', 'Confirm')) ?></button>
                </div>
                <div class="alert alert-warning mt-2" x-show="backupCodes" x-cloak>
                    <span>🔑</span>
                    <div><strong><?= e(__('profile.2fa_backup', 'Backup codes — store them safely, shown only once:')) ?></strong>
                        <div style="font-family:monospace" x-text="backupCodes ? backupCodes.join('  ') : ''"></div></div>
                </div>
            <?php else: ?>
                <p><span class="badge badge-success">✓ <?= e(__('profile.2fa_on', '2FA is enabled')) ?></span></p>
                <form x-on:submit.prevent="
                    const pw = prompt('<?= e(__('profile.2fa_disable_confirm', 'Enter your password to disable 2FA')) ?>');
                    if (!pw) return;
                    kwc.fetch('<?= e(url('/profile/2fa/disable')) ?>', { method: 'POST', json: { password: pw } })
                        .then(r => { kwc.toast(r.data.message, r.ok ? 'success' : 'danger'); if (r.ok) setTimeout(() => location.reload(), 1000); })">
                    <button class="btn btn-danger btn-sm" type="submit"><?= e(__('profile.2fa_disable', 'Disable 2FA')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php View::end(); ?>
