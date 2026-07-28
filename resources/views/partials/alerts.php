<?php
/** Flash messages + validation error summary. */
$flashSuccess = flash('success');
$flashError = flash('error');
$flashWarning = flash('warning');
$errors = flash_errors();
?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success" data-auto-dismiss role="alert"><span>✅</span><div><?= e($flashSuccess) ?></div></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-danger" role="alert"><span>⚠️</span><div><?= e($flashError) ?></div></div>
<?php endif; ?>
<?php if ($flashWarning): ?>
    <div class="alert alert-warning" role="alert"><span>⚠️</span><div><?= e($flashWarning) ?></div></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <span>⚠️</span>
        <div>
            <?php foreach ($errors as $fieldErrors): ?>
                <?php foreach ((array) $fieldErrors as $message): ?>
                    <div><?= e($message) ?></div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
