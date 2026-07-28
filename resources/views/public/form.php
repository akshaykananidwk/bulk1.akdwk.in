<?php
/** Public lead form (standalone page, no app layout). */
$settings = $settings ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($form['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="auth-page">
    <div class="glass auth-card">
        <h1 style="font-size:1.2rem" class="text-center"><?= e($settings['title'] ?? $form['name']) ?></h1>
        <?php if (!empty($settings['subtitle'])): ?>
            <p class="text-center text-muted"><?= e($settings['subtitle']) ?></p>
        <?php endif; ?>

        <?php \App\Core\View::partial('partials/alerts'); ?>

        <form method="post" action="<?= e($action) ?>">
            <?php foreach ($fields as $field): ?>
                <div class="form-group">
                    <label class="form-label" for="f_<?= e($field['key']) ?>">
                        <?= e($field['label']) ?><?= (int) $field['is_required'] === 1 ? ' *' : '' ?>
                    </label>
                    <?php $options = json_decode((string) ($field['options'] ?? '[]'), true) ?: []; ?>
                    <?php if ($field['type'] === 'dropdown'): ?>
                        <select class="input" id="f_<?= e($field['key']) ?>" name="f_<?= e($field['key']) ?>" <?= (int) $field['is_required'] === 1 ? 'required' : '' ?>>
                            <option value=""><?= e(__('common.choose', 'Choose…')) ?></option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= e((string) $option) ?>" <?= old('f_' . $field['key']) === (string) $option ? 'selected' : '' ?>><?= e((string) $option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($field['type'] === 'checkbox'): ?>
                        <label class="checkbox-row"><input type="checkbox" id="f_<?= e($field['key']) ?>" name="f_<?= e($field['key']) ?>" value="yes"> <?= e($field['label']) ?></label>
                    <?php else: ?>
                        <input class="input" id="f_<?= e($field['key']) ?>" name="f_<?= e($field['key']) ?>"
                               type="<?= $field['type'] === 'number' ? 'number' : ($field['type'] === 'date' ? 'date' : ($field['key'] === 'phone' || $field['type'] === 'phone' ? 'tel' : 'text')) ?>"
                               value="<?= e(old('f_' . $field['key'])) ?>"
                               <?= (int) $field['is_required'] === 1 ? 'required' : '' ?>>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="form-group">
                <label class="form-label"><?= e(__('auth.captcha', 'Quick check')) ?>: <?= e((string) $captchaA) ?> + <?= e((string) $captchaB) ?> = ?</label>
                <input class="input" type="number" name="captcha" required>
            </div>

            <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e($settings['button'] ?? __('forms.submit', 'Submit')) ?></button>
        </form>
    </div>
</div>
</body>
</html>
