<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="legal-page" style="max-width:640px">
    <h1><?= e(__('site.contact', 'Contact Us')) ?></h1>
    <p class="text-muted"><?= e(__('site.contact_lead', 'Questions about the platform, pricing, or a partnership? Send us a message — we reply within one business day.')) ?></p>

    <?php if (!empty($supportEmail)): ?>
        <p class="text-sm">📧 <strong><?= e(__('site.email_us', 'Email')) ?>:</strong> <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a></p>
    <?php endif; ?>

    <div class="card mt-4">
        <form method="post" action="<?= e(url('/contact')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="c-name"><?= e(__('fields.name', 'Name')) ?></label>
                <input class="input" id="c-name" name="name" value="<?= e(old('name')) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="c-email"><?= e(__('fields.email', 'Email')) ?></label>
                <input class="input" id="c-email" type="email" name="email" value="<?= e(old('email')) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="c-message"><?= e(__('site.message', 'Message')) ?></label>
                <textarea class="input" id="c-message" name="message" rows="5" required minlength="10"><?= e(old('message')) ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="c-captcha"><?= e(__('auth.captcha', 'Quick check')) ?>: <?= e((string) $captchaA) ?> + <?= e((string) $captchaB) ?> = ?</label>
                <input class="input" id="c-captcha" type="number" name="captcha" required>
            </div>
            <button class="btn btn-primary btn-lg" type="submit"><?= e(__('site.send_message', 'Send message')) ?></button>
        </form>
    </div>
</div>
<?php View::end(); ?>
