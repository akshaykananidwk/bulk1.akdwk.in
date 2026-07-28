<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <div>
        <h1><?= e(__('admin.meta_app', 'Meta App configuration')) ?></h1>
        <p class="text-muted text-sm" style="margin:0"><?= e(__('admin.meta_app_sub', 'Platform-wide WhatsApp Cloud API application used for Embedded Signup')) ?></p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <form method="post" action="<?= e(url('/admin/meta-app')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">App ID</label>
                <input class="input" name="app_id" value="<?= e($app['app_id'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">App Secret <?= $app ? '<span class="text-muted">(' . e(__('admin.leave_blank', 'leave blank to keep current')) . ')</span>' : '' ?></label>
                <input class="input" type="password" name="app_secret" autocomplete="new-password" <?= $app ? '' : 'required' ?> placeholder="<?= $app ? '••••••••••••' : '' ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Embedded Signup Config ID</label>
                <input class="input" name="config_id" value="<?= e($app['config_id'] ?? '') ?>" placeholder="123456789012345">
                <div class="form-hint"><?= e(__('admin.config_id_hint', 'From Meta App → WhatsApp → Embedded Signup configurations.')) ?></div>
            </div>
            <div class="form-group">
                <label class="form-label">System User Token <span class="text-muted">(<?= e(__('common.optional', 'optional')) ?>)</span></label>
                <textarea class="input" name="system_user_token" rows="2" placeholder="<?= $app && $app['system_user_token_encrypted'] ? '••••••••••••' : 'EAAG…' ?>"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Graph API version</label>
                <input class="input" name="api_version" value="<?= e($app['api_version'] ?? 'v21.0') ?>" required pattern="v\d+\.\d+">
            </div>
            <div class="form-group">
                <label class="form-label">Webhook verify token</label>
                <input class="input" name="webhook_verify_token" value="<?= e($app['webhook_verify_token'] ?? '') ?>" placeholder="<?= e(__('admin.auto_generated', 'auto-generated if blank')) ?>">
            </div>
            <button class="btn btn-primary" type="submit"><?= e(__('common.save', 'Save')) ?></button>
        </form>
    </div>

    <div class="card">
        <h3 class="card-title"><?= e(__('admin.webhook_setup', 'Webhook setup')) ?></h3>
        <p class="text-sm text-muted"><?= e(__('admin.webhook_hint', 'Configure these in Meta App → WhatsApp → Configuration:')) ?></p>
        <div class="form-group">
            <label class="form-label">Callback URL</label>
            <div class="input-group">
                <input class="input" readonly value="<?= e($webhookUrl) ?>">
                <button class="btn btn-outline" type="button" data-copy="<?= e($webhookUrl) ?>">📋</button>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Verify token</label>
            <div class="input-group">
                <input class="input" readonly value="<?= e($app['webhook_verify_token'] ?? '—') ?>">
                <?php if (!empty($app['webhook_verify_token'])): ?>
                    <button class="btn btn-outline" type="button" data-copy="<?= e($app['webhook_verify_token']) ?>">📋</button>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-sm text-muted"><?= e(__('admin.webhook_fields', 'Subscribe to webhook fields: messages, message_template_status_update, phone_number_quality_update, account_update, template_category_update.')) ?></p>
    </div>
</div>
<?php View::end(); ?>
