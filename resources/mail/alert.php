<?php /** Generic alert email. Vars: $title, $body, $link (optional) */ ?>
<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 560px; margin: 0 auto; color: #0f172a;">
    <div style="border: 1px solid #e2e8f0; border-left: 4px solid #F59E0B; border-radius: 12px; padding: 24px;">
        <h2 style="margin-top: 0;">⚠️ <?= e($title ?? 'Alert') ?></h2>
        <p style="white-space: pre-line;"><?= e($body ?? '') ?></p>
        <?php if (!empty($link)): ?>
            <p><a href="<?= e($link) ?>" style="color: #0F766E; font-weight: 600;"><?= e(__('mail.view_details', 'View details')) ?> →</a></p>
        <?php endif; ?>
        <p style="color: #64748b; font-size: 12px; margin-bottom: 0;">— <?= e(setting('app_name', 'Krishna WhatsApp Cloud')) ?></p>
    </div>
</div>
