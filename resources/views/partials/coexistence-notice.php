<?php
/**
 * Coexistence (WhatsApp Business app + Cloud API on one number) limitations.
 * Optional: $compact (bool) — one-line hint shown next to the connect buttons.
 */
$compact = $compact ?? false;
$limits = __('whatsapp.coex_limits', 'Not available on a coexistence number: groups, disappearing messages, view-once media, live location, broadcast lists, and linked companion devices (WhatsApp Web/Desktop, except the official app).');
$keepActive = __('whatsapp.coex_keep_active', 'Keep using the WhatsApp Business app on your phone regularly (open it at least once every 14 days), or Meta disconnects the number from the platform.');
?>
<?php if ($compact): ?>
    <p class="text-xs text-muted mt-2" style="max-width:720px">
        📲 <strong><?= e(__('whatsapp.coex_title_short', 'QR / Business App:')) ?></strong>
        <?= e(__('whatsapp.coex_compact', 'keeps your number working in the WhatsApp Business app on your phone and imports its contacts and the last 6 months of chats.')) ?>
        <?= e($limits) ?>
    </p>
<?php else: ?>
    <div class="alert alert-info" style="margin:0 1rem 1rem">
        <span>📲</span>
        <div class="text-sm">
            <strong><?= e(__('whatsapp.coex_title', 'Coexistence number — WhatsApp Business app + platform')) ?></strong>
            <ul style="margin:.35rem 0 0;padding-left:1.1rem">
                <li><?= e(__('whatsapp.coex_sync', 'Messages you send from the phone app appear here too, and contacts plus the last 6 months of chats were imported.')) ?></li>
                <li><?= e($keepActive) ?></li>
                <li><?= e($limits) ?></li>
            </ul>
        </div>
    </div>
<?php endif; ?>
