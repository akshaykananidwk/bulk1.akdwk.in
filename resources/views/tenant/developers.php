<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <div>
        <h1><?= e(__('nav.developers', 'API & Webhooks')) ?></h1>
        <p class="text-muted text-sm" style="margin:0"><?= e(__('api.base', 'Base URL')) ?>: <code><?= e($apiBase) ?></code> · <a href="<?= e($docsUrl) ?>" target="_blank"><?= e(__('api.docs', 'Interactive docs')) ?> ↗</a></p>
    </div>
</div>

<div class="grid-2" x-data="{ newKey: '' }">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= e(__('api.keys', 'API keys')) ?></h3>
        </div>

        <form x-on:submit.prevent="
            const form = new FormData($el);
            form.append('_token', kwc.csrf());
            fetch('<?= e(url('/tenant/developers/keys')) ?>', { method: 'POST', body: form, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(r => r.json()).then(d => {
                    if (d.success) { newKey = d.data.api_key; }
                    else { kwc.toast(d.message || 'Failed', 'danger'); }
                });
        ">
            <div class="grid-2">
                <div class="form-group"><label class="form-label"><?= e(__('api.key_name', 'Key name')) ?></label>
                    <input class="input" name="name" required placeholder="Zapier integration"></div>
                <div class="form-group"><label class="form-label"><?= e(__('api.rate_limit', 'Rate limit / min')) ?></label>
                    <input class="input" type="number" name="rate_limit" value="60" min="1" max="10000"></div>
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('api.scopes', 'Scopes')) ?></label>
                <?php foreach ($scopes as $scope): ?>
                    <label class="checkbox-row"><input type="checkbox" name="scopes[]" value="<?= e($scope) ?>" <?= $scope === 'messages.send' ? 'checked' : '' ?>> <code><?= e($scope) ?></code></label>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary" type="submit">＋ <?= e(__('api.create_key', 'Create key')) ?></button>
        </form>

        <div class="alert alert-success mt-4" x-show="newKey" x-cloak>
            <span>🔑</span>
            <div>
                <strong><?= e(__('api.key_once', 'Copy this key now — it will never be shown again.')) ?></strong>
                <div class="input-group mt-1">
                    <input class="input" readonly :value="newKey" style="font-family:monospace;font-size:.78rem">
                    <button class="btn btn-outline" type="button" x-on:click="navigator.clipboard.writeText(newKey); kwc.toast('Copied', 'success', 1500)">📋</button>
                </div>
            </div>
        </div>

        <div class="table-wrap mt-4" style="border:none">
            <table class="table">
                <thead><tr><th><?= e(__('fields.name', 'Name')) ?></th><th>Key ID</th><th><?= e(__('fields.status', 'Status')) ?></th><th><?= e(__('api.last_used', 'Last used')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($keys as $key): ?>
                    <tr>
                        <td><?= e($key['name']) ?></td>
                        <td><code class="text-xs"><?= e($key['key_id']) ?>.•••</code></td>
                        <td><span class="badge badge-<?= $key['status'] === 'active' ? 'success' : 'danger' ?>"><?= e($key['status']) ?></span></td>
                        <td class="text-sm text-muted"><?= $key['last_used_at'] ? e(time_ago((string) $key['last_used_at'])) : '—' ?></td>
                        <td>
                            <?php if ($key['status'] === 'active'): ?>
                                <form method="post" action="<?= e(url('/tenant/developers/keys/' . (int) $key['id'] . '/revoke')) ?>" data-confirm="<?= e(__('api.revoke_confirm', 'Revoke this key? Apps using it will stop working.')) ?>">
                                    <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= e(__('api.revoke', 'Revoke')) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($keys)): ?>
                    <tr><td colspan="5" class="text-center text-muted"><?= e(__('common.no_data', 'No data yet')) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?= e(__('api.quickstart', 'Quick start')) ?></h3>
        <p class="text-sm text-muted"><?= e(__('api.quickstart_note', 'Send a message with one HTTP call:')) ?></p>
<pre style="background:var(--surface-3);border-radius:10px;padding:.8rem;font-size:.75rem;overflow-x:auto">curl -X POST <?= e($apiBase) ?>/messages \
  -H "X-Api-Key: kwc_xxxx.yyyy" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "919812345678",
    "type": "text",
    "text": { "body": "Hello from the API! 🎉" }
  }'</pre>
        <p class="text-sm text-muted mt-2"><?= e(__('api.hooks_note', 'Outbound webhooks (message.received, message.status, campaign.completed…) are signed with X-KWC-Signature: sha256=HMAC(body, secret).')) ?></p>
        <h4 class="mt-4"><?= e(__('api.active_hooks', 'Active webhooks')) ?></h4>
        <?php foreach ($hooks as $hook): ?>
            <div class="text-sm"><code><?= e($hook['url']) ?></code>
                <span class="badge badge-<?= $hook['is_active'] ? 'success' : 'muted' ?>"><?= $hook['is_active'] ? 'on' : 'off' ?></span></div>
        <?php endforeach; ?>
        <?php if (empty($hooks)): ?><span class="text-sm text-muted"><?= e(__('api.no_hooks', 'None yet — create them via the API.')) ?></span><?php endif; ?>
    </div>
</div>
<?php View::end(); ?>
