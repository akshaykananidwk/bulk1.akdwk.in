<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <div>
        <h1><?= e(__('nav.contacts', 'Contacts')) ?></h1>
        <p class="text-muted text-sm" style="margin:0"><?= e(number_format((float) $result['total'])) ?> <?= e(__('contacts.total', 'contacts')) ?></p>
    </div>
    <div class="flex gap-2" x-data>
        <a class="btn btn-outline" href="<?= e(url('/tenant/contacts/export')) ?>">⬇ <?= e(__('common.export', 'Export')) ?></a>
        <button class="btn btn-outline" x-on:click="document.getElementById('import-modal').classList.remove('hidden')">⬆ <?= e(__('common.import', 'Import')) ?></button>
        <button class="btn btn-primary" x-on:click="document.getElementById('add-modal').classList.remove('hidden')">＋ <?= e(__('contacts.add', 'Add contact')) ?></button>
    </div>
</div>

<div class="card mb-4">
    <form method="get" action="<?= e(url('/tenant/contacts')) ?>" class="flex gap-2" style="flex-wrap:wrap">
        <input class="input" style="max-width:280px" type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('contacts.search', 'Search name, phone, email…')) ?>">
        <select class="input" style="max-width:180px" name="tag">
            <option value=""><?= e(__('contacts.all_tags', 'All tags')) ?></option>
            <?php foreach ($tags as $tag): ?>
                <option value="<?= (int) $tag['id'] ?>" <?= $tagId === (int) $tag['id'] ? 'selected' : '' ?>><?= e($tag['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="input" style="max-width:180px" name="stage">
            <option value=""><?= e(__('contacts.all_stages', 'All stages')) ?></option>
            <?php foreach (['lead', 'prospect', 'customer', 'vip', 'churned'] as $stageOption): ?>
                <option value="<?= e($stageOption) ?>" <?= $stage === $stageOption ? 'selected' : '' ?>><?= e(ucfirst($stageOption)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline" type="submit"><?= e(__('common.search', 'Search')) ?></button>
    </form>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th><?= e(__('fields.name', 'Name')) ?></th>
            <th><?= e(__('fields.phone', 'Phone')) ?></th>
            <th><?= e(__('contacts.stage', 'Stage')) ?></th>
            <th><?= e(__('contacts.score', 'Score')) ?></th>
            <th><?= e(__('contacts.opt_in', 'Opt-in')) ?></th>
            <th><?= e(__('contacts.last_message', 'Last message')) ?></th>
            <th><?= e(__('common.actions', 'Actions')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($result['data'] as $contact): ?>
            <tr>
                <td>
                    <div class="flex items-center gap-2">
                        <span class="avatar avatar-sm"><?= e(\App\Core\Str::initials($contact['name'] ?? $contact['phone'])) ?></span>
                        <div>
                            <div class="font-semi"><?= e($contact['name'] ?? '—') ?></div>
                            <div class="text-xs text-muted"><?= e($contact['email'] ?? '') ?></div>
                        </div>
                    </div>
                </td>
                <td class="tabular"><?= e($contact['phone']) ?></td>
                <td><span class="badge badge-primary"><?= e($contact['lifecycle_stage']) ?></span></td>
                <td class="tabular"><?= e((string) $contact['lead_score']) ?></td>
                <td><?= (int) $contact['opt_in'] === 1 ? '<span class="badge badge-success">✓</span>' : '<span class="badge badge-danger">✗ STOP</span>' ?></td>
                <td class="text-sm text-muted"><?= $contact['last_message_at'] ? e(time_ago((string) $contact['last_message_at'])) : '—' ?></td>
                <td>
                    <div class="flex gap-1">
                        <a class="btn btn-ghost btn-sm" href="<?= e(url('/tenant/inbox?contact=' . (int) $contact['id'])) ?>" title="<?= e(__('contacts.open_chat', 'Open chat')) ?>">💬</a>
                        <form method="post" action="<?= e(url('/tenant/contacts/' . (int) $contact['id'] . '/delete')) ?>" data-confirm="<?= e(__('contacts.delete_confirm', 'Delete this contact and their conversation history?')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm" type="submit" title="<?= e(__('common.delete', 'Delete')) ?>">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($result['data'])): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="icon">👥</div><p><?= e(__('contacts.empty', 'No contacts yet. Add or import your first contacts.')) ?></p></div></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php View::partial('partials/pagination', ['result' => $result, 'baseUrl' => url('/tenant/contacts') . '?q=' . rawurlencode($search) . '&tag=' . $tagId . '&stage=' . rawurlencode($stage)]); ?>

<!-- Add contact modal -->
<div id="add-modal" class="modal-backdrop hidden">
    <div class="modal">
        <div class="modal-header"><h3 style="margin:0"><?= e(__('contacts.add', 'Add contact')) ?></h3>
            <button class="btn btn-ghost btn-icon" onclick="document.getElementById('add-modal').classList.add('hidden')">✕</button></div>
        <form method="post" action="<?= e(url('/tenant/contacts')) ?>">
            <div class="modal-body">
                <?= csrf_field() ?>
                <div class="form-group"><label class="form-label"><?= e(__('fields.phone', 'Phone')) ?> *</label>
                    <input class="input" name="phone" required placeholder="9198XXXXXXXX"></div>
                <div class="form-group"><label class="form-label"><?= e(__('fields.name', 'Name')) ?></label>
                    <input class="input" name="name"></div>
                <div class="form-group"><label class="form-label"><?= e(__('fields.email', 'Email')) ?></label>
                    <input class="input" type="email" name="email"></div>
                <div class="form-group"><label class="form-label"><?= e(__('contacts.stage', 'Stage')) ?></label>
                    <select class="input" name="lifecycle_stage">
                        <?php foreach (['lead', 'prospect', 'customer', 'vip'] as $stageOption): ?>
                            <option value="<?= e($stageOption) ?>"><?= e(ucfirst($stageOption)) ?></option>
                        <?php endforeach; ?>
                    </select></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('add-modal').classList.add('hidden')"><?= e(__('common.cancel', 'Cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(__('common.save', 'Save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Import modal -->
<div id="import-modal" class="modal-backdrop hidden">
    <div class="modal">
        <div class="modal-header"><h3 style="margin:0"><?= e(__('contacts.import_title', 'Import contacts (CSV)')) ?></h3>
            <button class="btn btn-ghost btn-icon" onclick="document.getElementById('import-modal').classList.add('hidden')">✕</button></div>
        <form method="post" action="<?= e(url('/tenant/contacts/import')) ?>" enctype="multipart/form-data">
            <div class="modal-body">
                <?= csrf_field() ?>
                <div class="form-group"><label class="form-label"><?= e(__('contacts.csv_file', 'CSV file')) ?></label>
                    <input class="input" type="file" name="file" accept=".csv,.txt" required>
                    <div class="form-hint"><?= e(__('contacts.csv_hint', 'From Excel: File → Save As → CSV. Duplicates (by phone) are skipped automatically.')) ?></div></div>
                <div class="grid-3">
                    <div class="form-group"><label class="form-label"><?= e(__('contacts.col_phone', 'Phone column #')) ?></label>
                        <input class="input" type="number" name="map[phone]" value="0" min="0"></div>
                    <div class="form-group"><label class="form-label"><?= e(__('contacts.col_name', 'Name column #')) ?></label>
                        <input class="input" type="number" name="map[name]" value="1" min="0"></div>
                    <div class="form-group"><label class="form-label"><?= e(__('contacts.col_email', 'Email column #')) ?></label>
                        <input class="input" type="number" name="map[email]" placeholder="—" min="0"></div>
                </div>
                <label class="checkbox-row"><input type="checkbox" name="skip_header" value="1" checked> <?= e(__('contacts.skip_header', 'First row is a header')) ?></label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('import-modal').classList.add('hidden')"><?= e(__('common.cancel', 'Cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(__('common.import', 'Import')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php View::end(); ?>
