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
    <div class="card" style="max-width:640px">
        <h3 class="card-title">🔗 <?= e(__('update.settings', 'GitHub Update')) ?></h3>
        <form method="post" action="<?= e(url('/admin/updates/settings')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="github_owner" value="<?= e($settings['owner']) ?>">
            <div class="form-group">
                <label class="form-label"><?= e(__('update.repo_full', 'GitHub Repo (owner/repo)')) ?></label>
                <input class="input" name="github_repo"
                       value="<?= e($settings['owner'] !== '' ? $settings['owner'] . '/' . $settings['repo'] : $settings['repo']) ?>"
                       required placeholder="akshaykananidwk/bulk1.akdwk.in">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('update.branch', 'Branch')) ?></label>
                <input class="input" name="github_branch" value="<?= e($settings['branch']) ?>" required placeholder="main">
            </div>
            <div class="form-group">
                <label class="form-label"><?= e(__('update.token', 'GitHub Token')) ?></label>
                <input class="input" type="password" name="github_token" autocomplete="new-password" placeholder="<?= e($settings['token_masked'] ?: 'ghp_…') ?>">
                <div class="form-hint"><?= e(__('update.token_hint', 'Required for private repos. Leave blank to keep the saved token.')) ?></div>
            </div>
            <div class="flex gap-2">
                <button class="btn btn-primary" type="submit"><?= e(__('update.save_repo', 'Save Repo Settings')) ?></button>
                <button class="btn btn-accent" type="button" x-on:click="checkNow()" :disabled="checking">
                    <span x-show="!checking">🔍 <?= e(__('update.check_button', 'Check for Update')) ?></span>
                    <span x-show="checking"><?= e(__('update.checking', 'Checking…')) ?></span>
                </button>
                <button class="btn btn-outline" type="button" x-on:click="runDiagnostics()" :disabled="diagRunning">
                    🩺 <span x-text="diagRunning ? '<?= e(__('update.diag_running', 'Checking…')) ?>' : '<?= e(__('update.diagnostics', 'Problem? Run Diagnostics')) ?>'"></span>
                </button>
            </div>
        </form>

        <div class="mt-2 text-sm" x-show="checkError" x-text="checkError" style="color:var(--danger)" x-cloak></div>

        <template x-if="check && check.up_to_date">
            <div class="alert alert-success mt-4"><span>✅</span><div><?= e(__('update.up_to_date', 'You are up to date.')) ?></div></div>
        </template>

        <template x-if="check && check.configured === false">
            <div class="alert alert-warning mt-4"><span>⚙️</span><div><?= e(__('update.not_configured', 'Save the GitHub repo first, then check again.')) ?></div></div>
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
                        </div>
                    </div>
                </div>
                <div x-show="check.changelog && check.changelog.length">
                    <h4><?= e(__('update.changelog', 'Changelog')) ?></h4>
                    <ul class="text-sm"><template x-for="line in check.changelog"><li x-text="line"></li></template></ul>
                </div>
                <div class="mt-2">
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

    <?php if (!empty($history)): ?>
    <!-- History (only shown once at least one update has run) -->
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
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function updatesApp() {
    return {
        check: null, checking: false, checkError: '',
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

        checkNow() {
            this.checking = true; this.checkError = ''; this.check = null;
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
