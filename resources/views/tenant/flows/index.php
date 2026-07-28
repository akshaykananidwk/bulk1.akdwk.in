<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('nav.flows', 'Bot Flows')) ?></h1>
    <button class="btn btn-primary" x-data x-on:click="document.getElementById('new-flow-modal').classList.remove('hidden')">＋ <?= e(__('flows.new', 'New flow')) ?></button>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th><?= e(__('fields.name', 'Name')) ?></th>
            <th><?= e(__('flows.trigger', 'Trigger')) ?></th>
            <th><?= e(__('fields.status', 'Status')) ?></th>
            <th><?= e(__('flows.runs', 'Runs')) ?></th>
            <th><?= e(__('common.actions', 'Actions')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($flows as $flow): ?>
            <?php $triggerConfig = json_decode((string) ($flow['trigger_config'] ?? '{}'), true) ?: []; ?>
            <tr>
                <td><a class="font-semi" href="<?= e(url('/tenant/flows/' . (int) $flow['id'] . '/edit')) ?>"><?= e($flow['name']) ?></a></td>
                <td>
                    <span class="badge badge-info"><?= e($flow['trigger_type']) ?></span>
                    <?php if (!empty($triggerConfig['keyword'])): ?><code class="text-xs"><?= e($triggerConfig['keyword']) ?></code><?php endif; ?>
                </td>
                <td><span class="badge badge-<?= $flow['status'] === 'active' ? 'success' : 'muted' ?>"><?= e($flow['status']) ?></span></td>
                <td class="tabular"><?= e(number_format((float) $flow['runs_count'])) ?></td>
                <td>
                    <div class="flex gap-1">
                        <form method="post" action="<?= e(url('/tenant/flows/' . (int) $flow['id'] . '/toggle')) ?>"><?= csrf_field() ?>
                            <button class="btn btn-outline btn-sm" type="submit"><?= $flow['status'] === 'active' ? '⏸' : '▶' ?></button>
                        </form>
                        <form method="post" action="<?= e(url('/tenant/flows/' . (int) $flow['id'] . '/delete')) ?>" data-confirm="<?= e(__('flows.delete_confirm', 'Delete this flow?')) ?>"><?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm" type="submit">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($flows)): ?>
            <tr><td colspan="5"><div class="empty-state"><div class="icon">🤖</div><p><?= e(__('flows.empty', 'No bot flows yet. Create an auto-reply or a full chatbot.')) ?></p></div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="new-flow-modal" class="modal-backdrop hidden">
    <div class="modal">
        <div class="modal-header"><h3 style="margin:0"><?= e(__('flows.new', 'New flow')) ?></h3>
            <button class="btn btn-ghost btn-icon" onclick="document.getElementById('new-flow-modal').classList.add('hidden')">✕</button></div>
        <form method="post" action="<?= e(url('/tenant/flows')) ?>" x-data="{ trigger: 'keyword' }">
            <div class="modal-body">
                <?= csrf_field() ?>
                <div class="form-group"><label class="form-label"><?= e(__('fields.name', 'Name')) ?></label>
                    <input class="input" name="name" required placeholder="<?= e(__('flows.name_ph', 'Welcome flow')) ?>"></div>
                <div class="form-group"><label class="form-label"><?= e(__('flows.trigger', 'Trigger')) ?></label>
                    <select class="input" name="trigger_type" x-model="trigger">
                        <option value="keyword"><?= e(__('flows.trig_keyword', 'Keyword match')) ?></option>
                        <option value="any_message"><?= e(__('flows.trig_any', 'Any incoming message')) ?></option>
                        <option value="new_contact"><?= e(__('flows.trig_new', 'New contact')) ?></option>
                        <option value="api"><?= e(__('flows.trig_api', 'API call')) ?></option>
                    </select></div>
                <div class="form-group" x-show="trigger === 'keyword'"><label class="form-label"><?= e(__('flows.keyword', 'Keyword')) ?></label>
                    <input class="input" name="keyword" placeholder="hi"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('new-flow-modal').classList.add('hidden')"><?= e(__('common.cancel', 'Cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(__('common.create', 'Create')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php View::end(); ?>
