<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <div>
        <h1><?= e(__('whatsapp.title', 'WhatsApp Accounts')) ?></h1>
        <p class="text-muted text-sm" style="margin:0"><?= e(__('whatsapp.subtitle', 'Connect and manage your WhatsApp Business API numbers')) ?></p>
    </div>
</div>

<?php if (empty($wabas)): ?>
    <div class="card text-center" style="padding:3rem">
        <div style="font-size:3rem">📱</div>
        <h2><?= e(__('whatsapp.none_title', 'No WhatsApp account connected yet')) ?></h2>
        <p class="text-muted"><?= e(__('whatsapp.none_sub', 'Use the official Meta Embedded Signup, or connect manually with an existing token.')) ?></p>
        <div class="flex gap-2" style="justify-content:center">
            <?php if ($metaApp !== null && !empty($metaApp['config_id'])): ?>
                <button class="btn btn-primary btn-lg" id="fb-connect-btn">🔗 <?= e(__('whatsapp.connect_meta', 'Connect with Meta')) ?></button>
            <?php else: ?>
                <span class="badge badge-warning"><?= e(__('whatsapp.app_not_configured', 'Embedded Signup unavailable — platform Meta App not configured')) ?></span>
            <?php endif; ?>
            <button class="btn btn-outline btn-lg" x-data x-on:click="document.getElementById('manual-modal').classList.remove('hidden')">
                ⚙️ <?= e(__('whatsapp.connect_manual', 'Manual connect')) ?>
            </button>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($wabas as $waba): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h2 class="card-title"><?= e($waba['name'] ?: ('WABA ' . $waba['waba_id'])) ?></h2>
                    <span class="text-xs text-muted">ID: <?= e($waba['waba_id']) ?> · <?= e(__('whatsapp.mode', 'Mode')) ?>: <?= e($waba['token_mode']) ?></span>
                </div>
                <div class="flex gap-2 items-center">
                    <span class="badge badge-<?= $waba['status'] === 'active' ? 'success' : 'danger' ?>"><?= e($waba['status']) ?></span>
                    <form method="post" action="<?= e(url('/tenant/whatsapp/' . (int) $waba['id'] . '/refresh')) ?>"><?= csrf_field() ?>
                        <button class="btn btn-outline btn-sm" type="submit">🔄 <?= e(__('whatsapp.refresh', 'Refresh')) ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/tenant/whatsapp/' . (int) $waba['id'] . '/disconnect')) ?>" data-confirm="<?= e(__('whatsapp.disconnect_confirm', 'Disconnect this WhatsApp account?')) ?>"><?= csrf_field() ?>
                        <button class="btn btn-danger btn-sm" type="submit"><?= e(__('whatsapp.disconnect', 'Disconnect')) ?></button>
                    </form>
                </div>
            </div>
            <div class="table-wrap" style="border:none">
                <table class="table">
                    <thead><tr>
                        <th><?= e(__('whatsapp.number', 'Number')) ?></th>
                        <th><?= e(__('whatsapp.verified_name', 'Verified name')) ?></th>
                        <th><?= e(__('whatsapp.quality', 'Quality')) ?></th>
                        <th><?= e(__('whatsapp.tier', 'Messaging tier')) ?></th>
                        <th><?= e(__('fields.status', 'Status')) ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($numbers as $number): if ((int) $number['waba_account_id'] !== (int) $waba['id']) continue; ?>
                        <tr>
                            <td class="font-semi"><?= e($number['display_phone_number']) ?><?= $number['is_default'] ? ' ⭐' : '' ?></td>
                            <td><?= e($number['verified_name'] ?? '—') ?></td>
                            <td>
                                <?php $quality = strtoupper((string) ($number['quality_rating'] ?? '')); ?>
                                <span class="badge badge-<?= $quality === 'GREEN' ? 'success' : ($quality === 'RED' ? 'danger' : 'warning') ?>"><?= e($quality ?: '—') ?></span>
                            </td>
                            <td class="text-sm"><?= e($number['messaging_limit_tier'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $number['status'] === 'active' ? 'success' : 'muted' ?>"><?= e($number['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="flex gap-2 mb-4">
        <?php if ($metaApp !== null && !empty($metaApp['config_id'])): ?>
            <button class="btn btn-primary" id="fb-connect-btn">🔗 <?= e(__('whatsapp.connect_another', 'Connect another account')) ?></button>
        <?php endif; ?>
        <button class="btn btn-outline" x-data x-on:click="document.getElementById('manual-modal').classList.remove('hidden')">⚙️ <?= e(__('whatsapp.connect_manual', 'Manual connect')) ?></button>
    </div>
<?php endif; ?>

<div class="card">
    <h3 class="card-title"><?= e(__('whatsapp.webhook_info', 'Webhook configuration (for manual setups)')) ?></h3>
    <p class="text-sm text-muted"><?= e(__('whatsapp.webhook_hint', 'In the Meta App dashboard, set the webhook callback URL to:')) ?></p>
    <div class="input-group">
        <input class="input" type="text" readonly value="<?= e($webhookUrl) ?>">
        <button class="btn btn-outline" type="button" data-copy="<?= e($webhookUrl) ?>">📋</button>
    </div>
</div>

<!-- Manual connect modal -->
<div id="manual-modal" class="modal-backdrop hidden">
    <div class="modal">
        <div class="modal-header">
            <h3 style="margin:0"><?= e(__('whatsapp.connect_manual', 'Manual connect')) ?></h3>
            <button class="btn btn-ghost btn-icon" onclick="document.getElementById('manual-modal').classList.add('hidden')">✕</button>
        </div>
        <form method="post" action="<?= e(url('/tenant/whatsapp/manual-connect')) ?>">
            <div class="modal-body">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label">WABA ID</label>
                    <input class="input" name="waba_id" required placeholder="102xxxxxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number ID</label>
                    <input class="input" name="phone_number_id" required placeholder="119xxxxxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('whatsapp.permanent_token', 'Permanent access token')) ?></label>
                    <textarea class="input" name="access_token" required rows="3" placeholder="EAAG..."></textarea>
                    <div class="form-hint"><?= e(__('whatsapp.token_hint', 'System-user token with whatsapp_business_messaging + whatsapp_business_management permissions. Stored encrypted.')) ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('manual-modal').classList.add('hidden')"><?= e(__('common.cancel', 'Cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(__('whatsapp.connect', 'Connect')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($metaApp !== null && !empty($metaApp['config_id'])): ?>
<script>
(function () {
    var btn = document.getElementById('fb-connect-btn');
    if (!btn) { return; }

    var sessionInfo = null;

    // Capture WABA + phone number IDs from the Embedded Signup message event
    window.addEventListener('message', function (event) {
        if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') { return; }
        try {
            var data = JSON.parse(event.data);
            if (data.type === 'WA_EMBEDDED_SIGNUP' && data.event === 'FINISH') {
                sessionInfo = data.data; // { waba_id, phone_number_id }
            }
        } catch (e) { /* other messages */ }
    });

    function launchSignup() {
        FB.login(function (response) {
            if (response.authResponse && response.authResponse.code) {
                if (!sessionInfo || !sessionInfo.waba_id) {
                    kwc.toast('<?= e(__('whatsapp.signup_incomplete', 'Signup incomplete — please try again.')) ?>', 'danger');
                    return;
                }
                kwc.fetch('<?= e(url('/tenant/whatsapp/embedded-callback')) ?>', {
                    method: 'POST',
                    json: {
                        code: response.authResponse.code,
                        waba_id: sessionInfo.waba_id,
                        phone_number_id: sessionInfo.phone_number_id
                    }
                }).then(function (result) {
                    if (result.ok) {
                        kwc.toast(result.data.message || 'Connected!', 'success');
                        setTimeout(function () { window.location.reload(); }, 1200);
                    } else {
                        kwc.toast(result.data.message || 'Connection failed', 'danger');
                    }
                });
            }
        }, {
            config_id: '<?= e($metaApp['config_id']) ?>',
            response_type: 'code',
            override_default_response_type: true,
            extras: { setup: {}, featureType: '', sessionInfoVersion: '3' }
        });
    }

    btn.addEventListener('click', function () {
        if (window.FB) { launchSignup(); return; }
        // Load the Facebook JS SDK on demand (external by necessity — Meta requirement)
        window.fbAsyncInit = function () {
            FB.init({
                appId: '<?= e($metaApp['app_id']) ?>',
                autoLogAppEvents: true,
                xfbml: false,
                version: '<?= e($metaApp['api_version']) ?>'
            });
            launchSignup();
        };
        var script = document.createElement('script');
        script.src = 'https://connect.facebook.net/en_US/sdk.js';
        script.async = true;
        script.defer = true;
        script.crossOrigin = 'anonymous';
        document.head.appendChild(script);
    });
})();
</script>
<?php endif; ?>
<?php View::end(); ?>
