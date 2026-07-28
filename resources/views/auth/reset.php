<?php use App\Core\Layout; use App\Core\View; Layout::title(__('auth.reset_title', 'Choose new password')); ?>
<?php View::start('content'); ?>
<h1 class="text-center" style="font-size:1.2rem"><?= e(__('auth.reset_heading', 'Choose a new password')) ?></h1>

<form method="post" action="<?= e(url('/reset-password')) ?>" class="mt-4">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
    <div class="form-group">
        <label class="form-label" for="password"><?= e(__('fields.password', 'New password')) ?></label>
        <input class="input" type="password" id="password" name="password" required minlength="8" autofocus>
    </div>
    <div class="form-group">
        <label class="form-label" for="password_confirmation"><?= e(__('fields.password_confirmation', 'Confirm password')) ?></label>
        <input class="input" type="password" id="password_confirmation" name="password_confirmation" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.reset_button', 'Update password')) ?></button>
</form>
<?php View::end(); ?>
