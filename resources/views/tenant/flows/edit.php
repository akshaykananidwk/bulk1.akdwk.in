<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <div>
        <h1><?= e($flow['name']) ?></h1>
        <p class="text-muted text-sm" style="margin:0">v<?= (int) $flow['version'] ?> ·
            <span class="badge badge-<?= $flow['status'] === 'active' ? 'success' : 'muted' ?>"><?= e($flow['status']) ?></span></p>
    </div>
    <div class="flex gap-2">
        <form method="post" action="<?= e(url('/tenant/flows/' . (int) $flow['id'] . '/toggle')) ?>"><?= csrf_field() ?>
            <button class="btn btn-outline" type="submit"><?= $flow['status'] === 'active' ? '⏸ ' . e(__('flows.pause', 'Pause')) : '▶ ' . e(__('flows.activate', 'Activate')) ?></button>
        </form>
        <button class="btn btn-primary" id="save-flow-btn">💾 <?= e(__('common.save', 'Save')) ?></button>
    </div>
</div>

<div class="grid-2" x-data="{ tab: 'json' }">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= e(__('flows.definition', 'Flow definition')) ?></h3>
        </div>
        <p class="text-sm text-muted"><?= e(__('flows.json_hint', 'The flow is a JSON graph of nodes. Node types: start, send_message, send_template, buttons, list, ask_question, wait_for_reply, condition, switch, delay, wait_until, set_variable, math, http_request, tag_contact, untag, update_field, lead_score, add_to_group, remove_from_group, assign_agent, handover, notify_team, send_email, otp_send, otp_verify, order_lookup, jump_to_flow, webhook_out, end.')) ?></p>
        <textarea class="input" id="flow-json" rows="24" style="font-family:monospace;font-size:.8rem" spellcheck="false"><?= e(json_encode(json_decode((string) $flow['definition'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
        <div class="form-hint mt-1"><?= e(__('flows.example', 'Example node: "n2": {"type":"buttons","config":{"text":"Choose:","buttons":[{"id":"a","title":"Catalog"},{"id":"b","title":"Support"}]},"branches":{"a":"n3","b":"n4","timeout":"n5"}}')) ?></div>
    </div>

    <div>
        <div class="card mb-4">
            <h3 class="card-title"><?= e(__('flows.recent_runs', 'Recent runs')) ?></h3>
            <div class="table-wrap" style="border:none">
                <table class="table">
                    <thead><tr><th>#</th><th><?= e(__('flows.node', 'Node')) ?></th><th><?= e(__('fields.status', 'Status')) ?></th><th><?= e(__('flows.started', 'Started')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td class="tabular"><?= (int) $run['id'] ?></td>
                            <td><code class="text-xs"><?= e($run['current_node'] ?? '—') ?></code></td>
                            <td><span class="badge badge-<?= $run['status'] === 'completed' ? 'success' : ($run['status'] === 'failed' ? 'danger' : 'info') ?>"><?= e($run['status']) ?></span></td>
                            <td class="text-sm text-muted"><?= e(time_ago((string) $run['started_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($runs)): ?>
                        <tr><td colspan="4" class="text-center text-muted"><?= e(__('flows.no_runs', 'No runs yet')) ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title"><?= e(__('flows.resources', 'Available resources')) ?></h3>
            <div class="text-sm">
                <strong><?= e(__('flows.approved_templates', 'Approved templates')) ?>:</strong>
                <?php foreach ($templates as $template): ?>
                    <div><code><?= (int) $template['id'] ?></code> — <?= e($template['name']) ?> (<?= e($template['language']) ?>)</div>
                <?php endforeach; ?>
                <?php if (empty($templates)): ?><span class="text-muted"><?= e(__('common.no_data', 'No data yet')) ?></span><?php endif; ?>
                <div class="mt-2"><strong><?= e(__('flows.tags', 'Tags')) ?>:</strong>
                <?php foreach ($tags as $tag): ?>
                    <span class="badge badge-muted"><code><?= (int) $tag['id'] ?></code> <?= e($tag['name']) ?></span>
                <?php endforeach; ?>
                </div>
                <div class="mt-2 text-muted"><?= e(__('flows.vars_hint', 'Variables: {{contact.name}}, {{contact.phone}}, {{reply}}, {{trigger.message}}, and anything you set_variable.')) ?></div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('save-flow-btn').addEventListener('click', function () {
    var raw = document.getElementById('flow-json').value;
    try { JSON.parse(raw); } catch (e) { kwc.toast('<?= e(__('flows.bad_json', 'Invalid JSON: ')) ?>' + e.message, 'danger', 6000); return; }
    var form = new FormData();
    form.append('definition', raw);
    form.append('_token', kwc.csrf());
    fetch('<?= e(url('/tenant/flows/' . (int) $flow['id'])) ?>', {
        method: 'POST', body: form,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => {
        if (d.success) { kwc.toast('<?= e(__('flows.saved', 'Flow saved')) ?> (v' + d.data.version + ')', 'success'); }
        else { kwc.toast(d.message || 'Save failed', 'danger', 6000); }
    });
});
</script>
<?php View::end(); ?>
