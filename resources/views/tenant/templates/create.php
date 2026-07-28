<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header"><h1><?= e(__('templates.new', 'New template')) ?></h1></div>

<form method="post" action="<?= e(url('/tenant/templates')) ?>"
      x-data="{ header: '<?= e(old('header')) ?>', body: '<?= e(old('body')) ?>', footer: '<?= e(old('footer')) ?>' }">
    <?= csrf_field() ?>
    <div class="grid-2">
        <div class="card">
            <div class="form-group">
                <label class="form-label">WABA</label>
                <select class="input" name="waba_account_id" required>
                    <?php foreach ($wabas as $waba): ?>
                        <option value="<?= (int) $waba['id'] ?>"><?= e($waba['name'] ?: $waba['waba_id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label"><?= e(__('templates.name', 'Template name')) ?></label>
                    <input class="input" name="name" value="<?= e(old('name')) ?>" required pattern="[a-z0-9_]+" placeholder="order_update_1">
                    <div class="form-hint"><?= e(__('templates.name_hint', 'Lowercase letters, numbers, underscores only.')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('templates.language', 'Language')) ?></label>
                    <select class="input" name="language">
                        <option value="en">English (en)</option>
                        <option value="en_US">English US (en_US)</option>
                        <option value="gu">ગુજરાતી (gu)</option>
                        <option value="hi">हिन्दी (hi)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.category', 'Category')) ?></label>
                <select class="input" name="category">
                    <option value="UTILITY">UTILITY — <?= e(__('templates.cat_utility', 'transactional updates')) ?></option>
                    <option value="MARKETING">MARKETING — <?= e(__('templates.cat_marketing', 'promotions & offers')) ?></option>
                    <option value="AUTHENTICATION">AUTHENTICATION — <?= e(__('templates.cat_auth', 'OTP codes')) ?></option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.header', 'Header')) ?> <span class="text-muted">(<?= e(__('common.optional', 'optional')) ?>)</span></label>
                <input class="input" name="header" x-model="header" maxlength="60">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.body', 'Body')) ?></label>
                <textarea class="input" name="body" x-model="body" required rows="5" maxlength="1024"
                          placeholder="Hello {{1}}, your order {{2}} has been shipped! 🚚"></textarea>
                <div class="form-hint"><?= e(__('templates.body_hint', 'Use {{1}}, {{2}}… for variables. Emojis allowed.')) ?></div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.footer', 'Footer')) ?> <span class="text-muted">(<?= e(__('common.optional', 'optional')) ?>)</span></label>
                <input class="input" name="footer" x-model="footer" maxlength="60">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('templates.buttons', 'Buttons')) ?> <span class="text-muted">(<?= e(__('common.optional', 'optional')) ?>)</span></label>
                <div class="grid-2">
                    <input class="input" name="buttons[0][text]" placeholder="<?= e(__('templates.btn1', 'Button 1 text (quick reply)')) ?>" maxlength="25">
                    <input class="input" name="buttons[1][text]" placeholder="<?= e(__('templates.btn2', 'Button 2 text (quick reply)')) ?>" maxlength="25">
                </div>
            </div>
            <button class="btn btn-primary btn-lg" type="submit">📤 <?= e(__('templates.submit', 'Submit to Meta for approval')) ?></button>
        </div>

        <!-- Live WhatsApp-style preview -->
        <div>
            <div class="card" style="background:var(--wa-chat-bg)">
                <h3 class="card-title"><?= e(__('templates.live_preview', 'Live preview')) ?></h3>
                <div class="bubble bubble-in" style="max-width:100%">
                    <strong x-show="header" x-text="header" style="display:block"></strong>
                    <span x-text="body || '<?= e(__('templates.preview_placeholder', 'Your message will appear here…')) ?>'" style="white-space:pre-wrap"></span>
                    <div class="text-xs text-muted" x-show="footer" x-text="footer" style="margin-top:.3rem"></div>
                    <div class="meta"><span><?= e(date('h:i A')) ?></span></div>
                </div>
            </div>
            <div class="alert alert-info mt-4">
                <span>💡</span>
                <div><?= e(__('templates.tips', 'Approval tips: avoid spammy words, keep variables in context, and match the category honestly — miscategorised templates get rejected.')) ?></div>
            </div>
        </div>
    </div>
</form>
<?php View::end(); ?>
