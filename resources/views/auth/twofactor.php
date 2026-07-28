<?php use App\Core\Layout; use App\Core\View; Layout::title(__('auth.2fa_title', 'Two-factor verification')); ?>
<?php View::start('content'); ?>
<h1 class="text-center" style="font-size:1.2rem"><?= e(__('auth.2fa_heading', 'Enter your 6-digit code')) ?></h1>
<p class="text-center text-muted mb-4"><?= e(__('auth.2fa_sub', 'Open your authenticator app, or use a backup code')) ?></p>

<form method="post" action="<?= e(url('/2fa')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <input class="input text-center" style="font-size:1.4rem; letter-spacing:.4em" type="text" name="code"
               inputmode="numeric" autocomplete="one-time-code" maxlength="8" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.2fa_verify', 'Verify')) ?></button>
</form>

<p class="text-center text-sm mt-4"><a href="<?= e(url('/login')) ?>">← <?= e(__('auth.back_to_login', 'Back to sign in')) ?></a></p>
<?php View::end(); ?>
