<?php use App\Core\Layout; use App\Core\View; Layout::title(__('auth.forgot_title', 'Forgot password')); ?>
<?php View::start('content'); ?>
<h1 class="text-center" style="font-size:1.2rem"><?= e(__('auth.forgot_heading', 'Reset your password')) ?></h1>
<p class="text-center text-muted mb-4"><?= e(__('auth.forgot_sub', "Enter your email and we'll send a reset link")) ?></p>

<form method="post" action="<?= e(url('/forgot-password')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="email"><?= e(__('fields.email', 'Email')) ?></label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.send_link', 'Send reset link')) ?></button>
</form>

<p class="text-center text-sm mt-4"><a href="<?= e(url('/login')) ?>">← <?= e(__('auth.back_to_login', 'Back to sign in')) ?></a></p>
<?php View::end(); ?>
