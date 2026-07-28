<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypt;
use App\Core\DB;
use App\Core\Hash;
use App\Core\Layout;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\View;
use App\Services\Updater\GithubClient;
use App\Services\Updater\IntegrityChecker;
use App\Services\Updater\UpdateChecker;
use App\Services\Updater\UpdateInstaller;

/**
 * Admin → System → Updates. Super-admin + update.manage + password
 * re-confirmation before "Update Now".
 */
final class UpdateController extends Controller
{
    public function index(Request $request): never
    {
        $tokenEncrypted = (string) setting('update_github_token', '');
        $tokenMasked = '';
        if ($tokenEncrypted !== '') {
            $token = Crypt::decrypt($tokenEncrypted);
            $tokenMasked = $token !== null ? Crypt::mask($token) : '(cannot decrypt — re-enter)';
        }

        Layout::title(__('admin.updates', 'Updates'));
        View::render('admin/updates', [
            'settings' => [
                'owner' => (string) setting('update_github_owner', ''),
                'repo' => (string) setting('update_github_repo', ''),
                'branch' => (string) setting('update_github_branch', 'main'),
                'channel' => (string) setting('update_channel', 'branch'),
                'auto_check' => (string) setting('update_auto_check', '1'),
                'auto_install' => (string) setting('update_auto_install', '0'),
                'notify_email' => (string) setting('update_notify_email', ''),
                'keep_backups' => (string) setting('update_keep_backups', '5'),
                'token_masked' => $tokenMasked,
            ],
            'installedVersion' => (string) setting('installed_version', app_version()),
            'installedSha' => (string) setting('installed_commit_sha', ''),
            'lastChecked' => (string) setting('update_last_checked_at', ''),
            'history' => DB::table('update_history')->orderBy('id', 'DESC')->limit(20)->get(),
            'updateRunning' => is_file(STORAGE_PATH . '/locks/update.lock'),
        ], 'layouts/admin');
    }

    public function saveSettings(Request $request): never
    {
        // GitHub repo names may contain dots (e.g. bulk1.akdwk.in). A combined
        // "owner/repo" value in the repo field is accepted and split.
        $combined = $request->str('github_repo');
        if (str_contains($combined, '/')) {
            [$ownerPart, $repoPart] = array_pad(explode('/', trim($combined, '/'), 2), 2, '');
            if ($ownerPart !== '' && $repoPart !== '') {
                $request->merge(['github_owner' => $ownerPart, 'github_repo' => $repoPart]);
            }
        }

        $data = $this->validate($request, [
            'github_owner' => 'required|regex:/^[A-Za-z0-9-]+$/|max:100',
            'github_repo' => 'required|regex:/^[A-Za-z0-9_.-]+$/|max:100',
            'github_branch' => 'required|string|max:100',
            'github_token' => 'nullable|string|max:255',
            'update_channel' => 'required|in:branch,release_tag',
            'notify_email' => 'nullable|email',
            'keep_backups' => 'required|integer|between:1,20',
        ]);

        set_setting('update_github_owner', (string) $data['github_owner']);
        set_setting('update_github_repo', (string) $data['github_repo']);
        set_setting('update_github_branch', (string) $data['github_branch']);
        set_setting('update_channel', (string) $data['update_channel']);
        set_setting('update_auto_check', $request->bool('auto_check') ? '1' : '0');
        set_setting('update_auto_install', $request->bool('auto_install') ? '1' : '0');
        set_setting('update_notify_email', (string) ($data['notify_email'] ?? ''));
        set_setting('update_keep_backups', (string) $data['keep_backups']);

        // Token only overwritten when a new one is supplied (masked in UI)
        if (!empty($data['github_token']) && !str_contains((string) $data['github_token'], '•')) {
            set_setting('update_github_token', Crypt::encrypt((string) $data['github_token']));
        }

        // Allow manually pinning the installed SHA (first-time setup)
        $pinnedSha = $request->str('installed_sha');
        if ($pinnedSha !== '' && preg_match('/^[0-9a-f]{7,40}$/i', $pinnedSha)) {
            set_setting('installed_commit_sha', strtolower($pinnedSha));
        }

        \App\Core\Cache::forget('update_check');
        audit_log('update.settings_saved');
        Redirect::to('/admin/updates')->with('success', __('update.settings_saved', 'Update settings saved.'))->send();
    }

    public function testConnection(Request $request): never
    {
        try {
            $client = new GithubClient();
            if (!$client->configured()) {
                $this->fail(__('update.not_configured', 'Enter the repository owner and name first.'), 422);
            }
            $info = $client->repoInfo();
            $this->ok([
                'name' => (string) ($info['full_name'] ?? ''),
                'private' => (bool) ($info['private'] ?? false),
                'default_branch' => (string) ($info['default_branch'] ?? ''),
                'pushed_at' => (string) ($info['pushed_at'] ?? ''),
            ], __('update.connected', 'Connected to repository.'));
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function check(Request $request): never
    {
        try {
            $result = UpdateChecker::check(true);
            $this->ok($result);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    /**
     * Update Now — requires password re-confirmation. Runs synchronously in
     * this request (long-running); the UI follows progress via /stream.
     */
    public function run(Request $request): never
    {
        $currentUser = Auth::user();
        if ($currentUser === null || !Hash::check($request->str('password'), (string) $currentUser['password'])) {
            $this->fail(__('auth.password_wrong', 'Password is incorrect.'), 422);
        }

        audit_log('update.run_started');
        UpdateInstaller::resetProgress();

        @set_time_limit(0);
        ignore_user_abort(true);
        session_write_close();

        try {
            $result = UpdateInstaller::run((int) $currentUser['id']);
            $this->ok($result, __('update.success', 'Update completed successfully.'));
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * SSE progress stream for the update UI.
     */
    public function stream(Request $request): never
    {
        session_write_close();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $lastCount = -1;
        $deadline = time() + 55;
        while (time() < $deadline) {
            if (connection_aborted()) {
                break;
            }
            $state = UpdateInstaller::progressState();
            $count = count((array) ($state['events'] ?? []));
            if ($count !== $lastCount) {
                $lastCount = $count;
                echo 'data: ' . json_encode($state, JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                if (in_array($state['state'] ?? '', ['success', 'failed', 'rolled_back', 'fatal'], true)) {
                    break;
                }
            } else {
                echo ": hb\n\n";
                flush();
            }
            usleep(800000);
        }
        exit;
    }

    public function rollback(Request $request): never
    {
        $currentUser = Auth::user();
        if ($currentUser === null || !Hash::check($request->str('password'), (string) $currentUser['password'])) {
            $this->fail(__('auth.password_wrong', 'Password is incorrect.'), 422);
        }
        try {
            @set_time_limit(0);
            UpdateInstaller::manualRollback((int) $request->route('id'));
            $this->ok(null, __('update.rolled_back', 'Rolled back to the retained backup.'));
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }

    public function verifyIntegrity(Request $request): never
    {
        try {
            $this->ok(IntegrityChecker::verify());
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }
    }
}
