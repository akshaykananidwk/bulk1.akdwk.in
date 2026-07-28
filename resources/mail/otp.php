<?php /** OTP email. Vars: $otp, $minutes */ ?>
<div style="font-family: 'Segoe UI', Arial, sans-serif; max-width: 480px; margin: 0 auto; color: #0f172a;">
    <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; text-align: center;">
        <h2 style="margin-top: 0;"><?= e(setting('app_name', 'Krishna WhatsApp Cloud')) ?></h2>
        <p><?= e(__('mail.otp_body', 'Your verification code is:')) ?></p>
        <div style="font-size: 32px; font-weight: 700; letter-spacing: 8px; background: #f1f5f9; border-radius: 10px; padding: 16px; margin: 16px 0;">
            <?= e($otp ?? '') ?>
        </div>
        <p style="color: #64748b; font-size: 13px;">
            <?= e(__('mail.otp_expiry', 'Valid for :m minutes. Never share this code.', ['m' => (string) ($minutes ?? 5)])) ?>
        </p>
    </div>
</div>
