<?php /** Welcome email. Vars: $name, $loginUrl */ ?>
<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 560px; margin: 0 auto; color: #0f172a;">
    <div style="background: #0F766E; color: #fff; border-radius: 12px 12px 0 0; padding: 24px; text-align: center;">
        <h1 style="margin: 0; font-size: 20px;">🦚 <?= e(setting('app_name', 'Krishna WhatsApp Cloud')) ?></h1>
    </div>
    <div style="border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px; padding: 24px;">
        <p><?= e(__('mail.welcome_hi', 'Namaste')) ?> <strong><?= e($name ?? '') ?></strong>,</p>
        <p><?= e(__('mail.welcome_body', 'Your workspace is ready! Connect your WhatsApp Business number and start chatting with your customers.')) ?></p>
        <p style="text-align: center; margin: 28px 0;">
            <a href="<?= e($loginUrl ?? url('/login')) ?>" style="background: #0F766E; color: #fff; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: 600;">
                <?= e(__('mail.welcome_cta', 'Open my workspace')) ?>
            </a>
        </p>
        <p style="color: #64748b; font-size: 13px;"><?= e(__('mail.welcome_footer', 'Need help? Just reply to this email.')) ?></p>
    </div>
</div>
