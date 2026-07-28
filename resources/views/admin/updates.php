<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<div class="page-header">
    <div>
        <h1><?= e(__('update.title', 'System Updates')) ?></h1>
        <p class="text-muted text-sm" style="margin:0">
            <?= e(__('update.installed', 'Installed')) ?>: <strong>v<?= e($installedVersion) ?></strong>
            <?php if ($installedSha !== ''): ?> · <code><?= e(substr($installedSha, 0, 7)) ?></code><?php endif; ?>
            <?php if ($lastChecked !== ''): ?> · <?= e(__('update.last_checked', 'Last checked')) ?> <?= e(\App\Core\DateHelper::display($lastChecked)) ?><?php endif; ?>
        </p>
    </div>
</div>

<div x-data="updatesApp()" x-init="init()">
    <div class="grid-2">
        <!-- Settings -->
        <div class="card">
            <h3 class="card-title"><?= e(__('update.settings', 'GitHub repository')) ?></h3>
            <form method="post" action="<?= e(url('/admin/updates/settings')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="github_owner" value="<?= e($settings['owner']) ?>">
                <div class="form-group">
                    <label class="form-label"><?= e(__('update.repo_full', 'GitHub repo (owner/repo)')) ?></label>
                    <input class="input" name="github_repo"
                           value="<?= e($settings['owner'] !== '' ? $settings['owner'] . '/' . $settings['repo'] : $settings['repo']) ?>"
                           required placeholder="akshaykananidwk/bulk1.akdwk.in">
                    <div class="form-hint"><?= e(__('update.repo_full_hint', 'Paste it exactly like the GitHub URL: owner/repository-name. Dots and dashes are fine.')) ?></div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label"><?= e(__('update.branch', 'Branch')) ?></label>
                        <input class="input" name="github_branch" value="<?= e($settings['branch']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('update.channel', 'Channel')) ?></label>
                        <select class="input" name="update_channel">
                            <option value="branch" <?= $settings['channel'] === 'branch' ? 'selected' : '' ?>><?= e(__('update.channel_branch', 'Branch (every commit)')) ?></option>
                            <option value="release_tag" <?= $settings['channel'] === 'release_tag' ? 'selected' : '' ?>><?= e(__('update.channel_release', 'Releases only')) ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(__('update.token', 'GitHub token')) ?> <span class="text-muted">(<?= e(__('update.token_private', 'required for private repos')) ?>)</span></label>
                    <input class="input" type="password" name="github_token" autocomplete="new-password" placeholder="<?= e($settings['token_masked'] ?: 'ghp_…') ?>">
                    <div class="form-hint"><?= e(__('update.token_hint', 'Stored AES-256-GCM encrypted; never shown or logged. Leave blank to keep the current token.')) ?></div>
                </div>
                <?php if ($installedSha === ''): ?>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('update.pin_sha', 'Installed commit SHA (first-time setup)')) ?></label>
                        <input class="input" name="installed_sha" placeholder="<?= e(__('update.pin_sha_hint', 'paste the commit your files came from')) ?>">
                    </div>
                <?php endif; ?>
                <div class="grid-2">
                    <label class="checkbox-row"><input type="checkbox" name="auto_check" value="1" <?= $settings['auto_check'] === '1' ? 'checked' : '' ?>> <?= e(__('update.auto_check', 'Check daily')) ?></label>
                    <label class="checkbox-row"><input type="checkbox" name="auto_install" value="1" <?= $settings['auto_install'] === '1' ? 'checked' : '' ?>> <?= e(__('update.auto_install', 'Auto-install (03:00)')) ?></label>
                </div>
                <div class="grid-2 mt-2">
                    <div class="form-group">
                        <label class="form-label"><?= e(__('update.notify_email', 'Notify email')) ?></label>
                        <input class="input" type="email" name="notify_email" value="<?= e($settings['notify_email']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= e(__('update.keep_backups', 'Backups to keep')) ?></label>
                        <input class="input" type="number" name="keep_backups" value="<?= e($settings['keep_backups']) ?>" min="1" max="20">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="btn btn-primary" type="submit"><?= e(__('common.save', 'Save')) ?></button>
                    <button class="btn btn-outline" type="button" x-on:click="testConnection()"><?= e(__('update.test', 'Test Connection')) ?></button>
                    <button class="btn btn-outline" type="button" x-on:click="runDiagnostics()" :disabled="diagRunning">
                        🩺 <span x-text="diagRunning ? '<?= e(__('update.diag_running', 'Checking…')) ?>' : '<?= e(__('update.diagnostics', 'Run Diagnostics')) ?>'"></span>
                    </button>
                </div>
                <div class="mt-2 text-sm" x-show="connMessage" x-text="connMessage" :style="connOk ? 'color:var(--success)' : 'color:var(--danger)'"></div>
            </form>

            <!-- Diagnostics results -->
            <div class="mt-4" x-show="diag.length" x-cloak>
                <h4 style="margin-bottom:.4rem">🩺 <?= e(__('update.diag_results', 'Diagnostics')) ?></h4>
                <div class="table-wrap" style="border:none">
                    <table class="table">
                        <tbody>
                        <template x-for="(d, i) in diag" :key="i">
                            <tr>
                                <td style="width:28px" x-text="d.ok === true ? '✅' : (d.ok === false ? '❌' : '⚠️')"></td>
                                <td>
                                    <span class="font-semi" x-text="d.label"></span>
                                    <div class="text-xs" style="word-break:break-word" :style="d.ok === false ? 'color:var(--danger)' : 'color:var(--text-muted)'" x-text="d.value"></div>
                                    <div class="text-xs" style="color:var(--warning)" x-show="d.ok !== true && d.hint" x-text="d.hint"></div>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Check / update card -->
        <div class="card">
            <h3 class="card-title"><?= e(__('update.check_title', 'Update status')) ?></h3>
            <button class="btn btn-primary" x-on:click="checkNow()" :disabled="checking">
                <span x-show="!checking">🔍 <?= e(__('update.check_button', 'Check for Update')) ?></span>
                <span x-show="checking"><?= e(__('update.checking', 'Checking…')) ?></span>
            </button>

            <template x-if="check && check.up_to_date">
                <div class="alert alert-success mt-4"><span>✅</span><div><?= e(__('update.up_to_date', 'You are up to date.')) ?></div></div>
            </template>

            <template x-if="check && !check.up_to_date && check.latest_sha">
                <div class="mt-4">
                    <div class="alert" :class="check.breaking ? 'alert-warning' : 'alert-info'">
                        <span x-text="check.breaking ? '⚠️' : '🚀'"></span>
                        <div>
                            <strong x-text="(check.installed_version || 'current') + ' → ' + (check.new_version || check.latest_sha.slice(0,7))"></strong>
                            <span x-show="check.breaking"> — <?= e(__('update.breaking', 'BREAKING CHANGES — read the changelog first!')) ?></span>
                            <div class="text-sm mt-1">
                                <div><strong><?= e(__('update.commit', 'Commit')) ?>:</strong> <code x-text="check.latest_sha.slice(0,7)"></code> · <span x-text="check.latest_author"></span> · <span x-text="check.latest_date"></span></div>
                                <div x-text="check.latest_commit_message" style="white-space:pre-line"></div>
                                <div class="mt-1">
                                    <span class="badge badge-info" x-text="(check.commits_behind || '?') + ' <?= e(__('update.commits', 'commits')) ?>'"></span>
                                    <span class="badge badge-info" x-text="(check.files_changed || '?') + ' <?= e(__('update.files', 'files')) ?>'"></span>
                                    <span class="badge badge-muted" x-text="'~' + Math.round((check.estimated_kb || 0) / 1024 * 10) / 10 + ' MB'"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div x-show="check.changelog && check.changelog.length">
                        <h4><?= e(__('update.changelog', 'Changelog')) ?></h4>
                        <ul class="text-sm"><template x-for="line in check.changelog"><li x-text="line"></li></template></ul>
                    </div>
                    <details x-show="check.files && check.files.length" class="text-sm">
                        <summary><?= e(__('update.changed_files', 'Changed files')) ?> (<span x-text="check.files ? check.files.length : 0"></span>)</summary>
                        <div style="max-height:200px;overflow-y:auto" class="mt-1">
                            <template x-for="f in check.files"><div><code x-text="f.status.charAt(0).toUpperCase()"></code> <span x-text="f.filename"></span></div></template>
                        </div>
                    </details>

                    <div class="mt-4">
                        <div class="form-group">
                            <label class="form-label"><?= e(__('update.confirm_password', 'Confirm your password to install')) ?></label>
                            <input class="input" type="password" x-model="password" autocomplete="current-password">
                        </div>
                        <button class="btn btn-accent btn-lg" x-on:click="runUpdate()" :disabled="running || !password">
                            🚀 <?= e(__('update.run', 'Update Now')) ?>
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="check && check.configured === false">
                <div class="alert alert-warning mt-4"><span>⚙️</span><div><?= e(__('update.not_configured', 'Enter the repository owner and name first.')) ?></div></div>
            </template>

            <div class="mt-2 text-sm" x-show="checkError" x-text="checkError" style="color:var(--danger)"></div>
        </div>
    </div>

    <!-- Live progress -->
    <div class="card mt-4" x-show="running || progress.length" x-cloak>
        <h3 class="card-title"><?= e(__('update.progress', 'Update progress')) ?></h3>
        <div style="font-family:monospace;font-size:.82rem;max-height:320px;overflow-y:auto;background:var(--surface-3);border-radius:10px;padding:.8rem" id="progress-log">
            <template x-for="(ev, i) in progress" :key="i">
                <div>
                    <span class="text-muted" x-text="'[' + ev.at + ']'"></span>
                    <span x-text="'[' + ev.step + '/11] ' + ev.message"
                          :style="ev.state === 'failed' || ev.state === 'fatal' ? 'color:var(--danger)' : (ev.state === 'success' ? 'color:var(--success)' : '')"></span>
                </div>
            </template>
        </div>
        <div class="mt-2" x-show="finalState === 'success'"><div class="alert alert-success"><span>🎉</span><div><?= e(__('update.done', 'Update complete! Reloading…')) ?></div></div></div>
        <div class="mt-2" x-show="finalState === 'rolled_back'"><div class="alert alert-warning"><span>↩️</span><div><?= e(__('update.rolled_back_note', 'The update failed and was rolled back automatically. Check the log above.')) ?></div></div></div>
        <div class="mt-2" x-show="finalState === 'fatal'"><div class="alert alert-danger"><span>🛑</span><div><?= e(__('update.fatal', 'Rollback failed — maintenance mode is ON. Follow the manual recovery instructions above and in the emailed alert.')) ?></div></div></div>
    </div>

    <!-- History -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title"><?= e(__('update.history', 'Update history')) ?></h3>
            <button class="btn btn-outline btn-sm" x-on:click="verifyIntegrity()"><?= e(__('update.verify', 'Verify Installation')) ?></button>
        </div>
        <div class="text-sm mb-2" x-show="integrity" x-cloak>
            <template x-if="integrity && integrity.clean"><span class="badge badge-success">✅ <?= e(__('update.integrity_clean', 'All files match the repository')) ?></span></template>
            <template x-if="integrity && !integrity.clean">
                <div>
                    <span class="badge badge-warning" x-text="integrity.missing.length + ' missing, ' + integrity.modified.length + ' modified, ' + integrity.extra.length + ' extra'"></span>
                    <details class="mt-1">
                        <summary><?= e(__('common.details', 'Details')) ?></summary>
                        <template x-for="f in integrity.missing"><div>❌ <span x-text="f"></span></div></template>
                        <template x-for="f in integrity.modified"><div>✏️ <span x-text="f"></span></div></template>
                        <template x-for="f in integrity.extra"><div>➕ <span x-text="f"></span></div></template>
                    </details>
                </div>
            </template>
        </div>
        <div class="table-wrap" style="border:none">
            <table class="table">
                <thead><tr>
                    <th><?= e(__('update.when', 'When')) ?></th><th><?= e(__('update.versions', 'Version')) ?></th>
                    <th><?= e(__('update.files', 'Files')) ?></th><th><?= e(__('update.migrations', 'Migrations')) ?></th>
                    <th><?= e(__('update.duration', 'Duration')) ?></th><th><?= e(__('fields.status', 'Status')) ?></th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($history as $row): ?>
                    <tr>
                        <td class="text-sm"><?= e(\App\Core\DateHelper::display((string) $row['created_at'])) ?></td>
                        <td><?= e($row['from_version']) ?> → <strong><?= e($row['to_version']) ?></strong>
                            <?php if ($row['commit_sha']): ?><code class="text-xs"><?= e(substr((string) $row['commit_sha'], 0, 7)) ?></code><?php endif; ?></td>
                        <td class="tabular"><?= e((string) $row['files_changed']) ?></td>
                        <td class="tabular"><?= e((string) $row['migrations_run']) ?></td>
                        <td class="tabular"><?= e((string) $row['duration_seconds']) ?>s</td>
                        <td><span class="badge badge-<?= $row['status'] === 'success' ? 'success' : ($row['status'] === 'rolled_back' ? 'warning' : 'danger') ?>"><?= e($row['status']) ?></span></td>
                        <td>
                            <?php if ($row['status'] === 'success' && $row['backup_file_path']): ?>
                                <button class="btn btn-outline btn-sm" x-on:click="manualRollback(<?= (int) $row['id'] ?>)">↩ <?= e(__('update.restore', 'Restore')) ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($history)): ?>
                    <tr><td colspan="7" class="text-center text-muted"><?= e(__('update.no_history', 'No updates run yet.')) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function updatesApp() {
    return {
        check: null, checking: false, checkError: '',
        connMessage: '', connOk: false,
        diag: [], diagRunning: false,
        password: '', running: <?= $updateRunning ? 'true' : 'false' ?>,
        progress: [], finalState: '', integrity: null,
        source: null,

        init() { if (this.running) { this.followProgress(); } },

        runDiagnostics() {
            this.diagRunning = true;
            this.diag = [];
            kwc.fetch('<?= e(url('/admin/updates/diagnostics')) ?>').then(r => {
                this.diagRunning = false;
                if (r.ok) { this.diag = r.data.data.checks; }
                else { kwc.toast(r.data.message || 'Diagnostics failed', 'danger'); }
            });
        },

        testConnection() {
            this.connMessage = '…';
            kwc.fetch('<?= e(url('/admin/updates/test-connection')) ?>', { method: 'POST', json: {} }).then(r => {
                this.connOk = r.ok;
                this.connMessage = r.ok
                    ? '✅ ' + r.data.data.name + (r.data.data.private ? ' (private)' : ' (public)') + ' · default: ' + r.data.data.default_branch + ' · pushed: ' + r.data.data.pushed_at
                    : '❌ ' + (r.data.message || 'Failed');
            });
        },

        checkNow() {
            this.checking = true; this.checkError = '';
            kwc.fetch('<?= e(url('/admin/updates/check')) ?>', { method: 'POST', json: {} }).then(r => {
                this.checking = false;
                if (r.ok) { this.check = r.data.data; }
                else { this.checkError = r.data.message || 'Check failed'; }
            });
        },

        runUpdate() {
            if (!confirm('<?= e(__('update.confirm', 'Start the update now? A full backup is taken first and the system rolls back automatically on failure.')) ?>')) { return; }
            this.running = true; this.progress = []; this.finalState = '';
            this.followProgress();
            kwc.fetch('<?= e(url('/admin/updates/run')) ?>', { method: 'POST', json: { password: this.password } }).then(r => {
                this.password = '';
                if (r.ok) {
                    this.finalState = 'success';
                    setTimeout(() => window.location.reload(), 2500);
                } else if (!this.finalState) {
                    this.finalState = 'rolled_back';
                    kwc.toast(r.data.message || 'Update failed', 'danger', 8000);
                }
                this.running = false;
            });
        },

        followProgress() {
            if (this.source) { this.source.close(); }
            const connect = () => {
                this.source = new EventSource('<?= e(url('/admin/updates/stream')) ?>');
                this.source.onmessage = (e) => {
                    try {
                        const data = JSON.parse(e.data);
                        this.progress = data.events || [];
                        const el = document.getElementById('progress-log');
                        if (el) { setTimeout(() => { el.scrollTop = el.scrollHeight; }, 50); }
                        if (['success', 'failed', 'rolled_back', 'fatal'].includes(data.state)) {
                            this.finalState = data.state === 'failed' ? 'rolled_back' : data.state;
                            this.source.close();
                        }
                    } catch (err) { /* heartbeat */ }
                };
                this.source.onerror = () => {
                    if (this.running) { setTimeout(connect, 2000); }
                    this.source.close();
                };
            };
            connect();
        },

        manualRollback(id) {
            const pw = prompt('<?= e(__('update.confirm_password', 'Confirm your password to install')) ?>');
            if (!pw) { return; }
            kwc.fetch('<?= e(url('/admin/updates/rollback')) ?>/' + id, { method: 'POST', json: { password: pw } }).then(r => {
                kwc.toast(r.data.message || (r.ok ? 'Restored' : 'Failed'), r.ok ? 'success' : 'danger');
                if (r.ok) { setTimeout(() => window.location.reload(), 1500); }
            });
        },

        verifyIntegrity() {
            this.integrity = null;
            kwc.fetch('<?= e(url('/admin/updates/verify')) ?>').then(r => {
                if (r.ok) { this.integrity = r.data.data; }
                else { kwc.toast(r.data.message || 'Verification failed', 'danger'); }
            });
        }
    };
}
</script>
<?php View::end(); ?>
