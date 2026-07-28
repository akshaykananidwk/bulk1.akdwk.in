<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <h1><?= e(__('nav.team', 'Team')) ?></h1>
    <button class="btn btn-primary" x-data x-on:click="document.getElementById('invite-modal').classList.remove('hidden')">＋ <?= e(__('team.invite', 'Invite member')) ?></button>
</div>

<div class="table-wrap">
    <table class="table">
        <thead><tr>
            <th><?= e(__('fields.name', 'Name')) ?></th>
            <th><?= e(__('team.role', 'Role')) ?></th>
            <th><?= e(__('team.presence', 'Presence')) ?></th>
            <th><?= e(__('fields.status', 'Status')) ?></th>
            <th><?= e(__('team.last_login', 'Last login')) ?></th>
            <th><?= e(__('common.actions', 'Actions')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($members as $member): ?>
            <tr>
                <td>
                    <div class="flex items-center gap-2">
                        <span class="avatar avatar-sm"><?= e(\App\Core\Str::initials($member['name'])) ?></span>
                        <div><div class="font-semi"><?= e($member['name']) ?></div>
                            <div class="text-xs text-muted"><?= e($member['email']) ?></div></div>
                    </div>
                </td>
                <td><span class="badge badge-primary"><?= e($member['role_name'] ?? '—') ?></span></td>
                <td><span class="dot dot-<?= e($member['presence'] ?? 'offline') ?>"></span> <?= e($member['presence'] ?? 'offline') ?></td>
                <td><span class="badge badge-<?= $member['status'] === 'active' ? 'success' : 'muted' ?>"><?= e($member['status']) ?></span></td>
                <td class="text-sm text-muted"><?= $member['last_login_at'] ? e(time_ago((string) $member['last_login_at'])) : '—' ?></td>
                <td>
                    <div class="flex gap-1" x-data>
                        <form method="post" action="<?= e(url('/tenant/team/' . (int) $member['id'] . '/update')) ?>" class="flex gap-1">
                            <?= csrf_field() ?>
                            <select class="input" name="role_id" style="width:auto;padding:.25rem .5rem;font-size:.78rem">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int) $role['id'] ?>" <?= ($member['role_slug'] ?? '') === $role['slug'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline btn-sm" type="submit"><?= e(__('common.save', 'Save')) ?></button>
                        </form>
                        <form method="post" action="<?= e(url('/tenant/team/' . (int) $member['id'] . '/remove')) ?>" data-confirm="<?= e(__('team.remove_confirm', 'Remove this member? Their conversations become unassigned.')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost btn-sm" type="submit">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="invite-modal" class="modal-backdrop hidden">
    <div class="modal">
        <div class="modal-header"><h3 style="margin:0"><?= e(__('team.invite', 'Invite member')) ?></h3>
            <button class="btn btn-ghost btn-icon" onclick="document.getElementById('invite-modal').classList.add('hidden')">✕</button></div>
        <form method="post" action="<?= e(url('/tenant/team/invite')) ?>">
            <div class="modal-body">
                <?= csrf_field() ?>
                <div class="form-group"><label class="form-label"><?= e(__('fields.name', 'Name')) ?></label>
                    <input class="input" name="name" required></div>
                <div class="form-group"><label class="form-label"><?= e(__('fields.email', 'Email')) ?></label>
                    <input class="input" type="email" name="email" required></div>
                <div class="form-group"><label class="form-label"><?= e(__('team.role', 'Role')) ?></label>
                    <select class="input" name="role_id">
                        <?php foreach ($roles as $role): ?>
                            <?php if ($role['slug'] === 'owner') continue; ?>
                            <option value="<?= (int) $role['id'] ?>"><?= e($role['name']) ?> — <?= e($role['description'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <p class="text-sm text-muted"><?= e(__('team.invite_note', 'A temporary password is emailed to the new member.')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('invite-modal').classList.add('hidden')"><?= e(__('common.cancel', 'Cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(__('team.invite_button', 'Send invite')) ?></button>
            </div>
        </form>
    </div>
</div>
<?php View::end(); ?>
