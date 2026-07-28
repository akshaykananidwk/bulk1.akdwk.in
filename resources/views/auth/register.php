<?php
use App\Core\Layout;
use App\Core\View;

Layout::title(__('auth.register_title', 'Create workspace'));
$a = random_int(2, 9);
$b = random_int(1, 9);
$_SESSION['captcha_answer'] = $a + $b;
?>
<?php View::start('content'); ?>
<h1 class="text-center" style="font-size:1.2rem"><?= e(__('auth.register_heading', 'Start your free trial')) ?></h1>
<p class="text-center text-muted mb-4"><?= e(__('auth.register_sub', 'No credit card required')) ?></p>

<form method="post" action="<?= e(url('/register')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="company"><?= e(__('fields.company', 'Business name')) ?></label>
        <input class="input" type="text" id="company" name="company" value="<?= e(old('company')) ?>" required>
    </div>
    <div class="form-group">
        <label class="form-label" for="name"><?= e(__('fields.name', 'Your name')) ?></label>
        <input class="input" type="text" id="name" name="name" value="<?= e(old('name')) ?>" required>
    </div>
    <div class="form-group">
        <label class="form-label" for="email"><?= e(__('fields.email', 'Email')) ?></label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required>
    </div>
    <div class="form-group">
        <label class="form-label" for="phone"><?= e(__('fields.phone', 'WhatsApp number')) ?> <span class="text-muted">(<?= e(__('common.optional', 'optional')) ?>)</span></label>
        <input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>" placeholder="9198XXXXXXXX">
    </div>
    <div class="form-group">
        <label class="form-label" for="password"><?= e(__('fields.password', 'Password')) ?></label>
        <input class="input" type="password" id="password" name="password" required minlength="8"
               x-data="{}" x-on:input="
                   const v = $el.value;
                   let s = 0;
                   if (v.length >= 8) s++;
                   if (/[A-Z]/.test(v)) s++;
                   if (/[0-9]/.test(v)) s++;
                   if (/[^A-Za-z0-9]/.test(v)) s++;
                   const bar = document.getElementById('pw-strength');
                   bar.style.width = (s * 25) + '%';
                   bar.style.background = s < 2 ? 'var(--danger)' : (s < 3 ? 'var(--warning)' : 'var(--success)');
               ">
        <div class="progress mt-1"><div class="progress-bar" id="pw-strength" style="width:0"></div></div>
        <div class="form-hint"><?= e(__('auth.password_hint', 'Min 8 characters with a mix of cases or numbers')) ?></div>
    </div>
    <div class="form-group">
        <label class="form-label" for="password_confirmation"><?= e(__('fields.password_confirmation', 'Confirm password')) ?></label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required>
    </div>
    <div class="form-group">
        <label class="form-label" for="captcha"><?= e(__('auth.captcha', 'Quick check')) ?>: <?= e((string) $a) ?> + <?= e((string) $b) ?> = ?</label>
        <input class="input" type="number" id="captcha" name="captcha" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg"><?= e(__('auth.register_button', 'Create my workspace')) ?></button>
</form>

<p class="text-center text-sm mt-4 text-muted">
    <?= e(__('auth.have_account', 'Already have an account?')) ?>
    <a href="<?= e(url('/login')) ?>"><?= e(__('auth.login_link', 'Sign in')) ?></a>
</p>
<?php View::end(); ?>
