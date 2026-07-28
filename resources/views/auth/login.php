<?php use App\Core\Layout; use App\Core\View; Layout::title(__('auth.login_title', 'Sign in')); ?>
<?php View::start('content'); ?>
<h1 class="text-center" style="font-size:1.2rem"><?= e(__('auth.login_heading', 'Welcome back')) ?></h1>
<p class="text-center text-muted mb-4"><?= e(__('auth.login_sub', 'Sign in to your workspace')) ?></p>

<form method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="email"><?= e(__('fields.email', 'Email')) ?></label>
        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="email">
    </div>
    <div class="form-group">
        <label class="form-label" for="password"><?= e(__('fields.password', 'Password')) ?></label>
        <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <div class="form-group flex items-center justify-between">
        <label class="checkbox-row"><input type="checkbox" name="remember" value="1"> <?= e(__('auth.remember', 'Remember me')) ?></label>
        <a href="<?= e(url('/forgot-password')) ?>" class="text-sm"><?= e(__('auth.forgot', 'Forgot password?')) ?></a>
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg"><?= e(__('auth.login_button', 'Sign in')) ?></button>
</form>

<?php if (setting('registration_enabled', '1') === '1'): ?>
    <p class="text-center text-sm mt-4 text-muted">
        <?= e(__('auth.no_account', "Don't have an account?")) ?>
        <a href="<?= e(url('/register')) ?>"><?= e(__('auth.register_link', 'Create workspace')) ?></a>
    </p>
<?php endif; ?>
<?php View::end(); ?>
