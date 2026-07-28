<?php

declare(strict_types=1);

namespace App\Services\Updater;

use App\Core\Cache;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Mail;

/**
 * "Check for Update": compares installed sha with the repo head and builds
 * the rich update card data (§12.2). Results cached 15 minutes.
 */
final class UpdateChecker
{
    public static function check(bool $force = false): array
    {
        if (!$force) {
            $cached = Cache::get('update_check');
            if (is_array($cached)) {
                return $cached;
            }
        }

        $client = new GithubClient();
        if (!$client->configured()) {
            return ['configured' => false];
        }

        $installedSha = (string) setting('installed_commit_sha', '');
        $installedVersion = (string) setting('installed_version', app_version());

        $head = $client->latestCommit();
        $headSha = (string) ($head['sha'] ?? '');

        $result = [
            'configured' => true,
            'checked_at' => date('c'),
            'installed_sha' => $installedSha,
            'installed_version' => $installedVersion,
            'latest_sha' => $headSha,
            'latest_commit_message' => (string) ($head['commit']['message'] ?? ''),
            'latest_author' => (string) ($head['commit']['author']['name'] ?? ''),
            'latest_date' => (string) ($head['commit']['author']['date'] ?? ''),
            'up_to_date' => $installedSha !== '' && $installedSha === $headSha,
            'first_install' => $installedSha === '',
        ];

        if (!$result['up_to_date']) {
            // version.json from the repo: version, min_php, breaking, changelog
            $versionRaw = $client->fileContents('version.json');
            $versionData = $versionRaw !== null ? (json_decode($versionRaw, true) ?: []) : [];
            $result['new_version'] = (string) ($versionData['version'] ?? '');
            $result['new_build'] = (int) ($versionData['build'] ?? 0);
            $result['min_php'] = (string) ($versionData['min_php'] ?? '8.3.0');
            $result['required_ext'] = (array) ($versionData['required_ext'] ?? []);
            $result['breaking'] = (bool) ($versionData['breaking'] ?? false);
            $result['changelog'] = (array) ($versionData['changelog'] ?? []);

            // Diff stats (only when we know the installed sha)
            if ($installedSha !== '') {
                try {
                    $comparison = $client->compare($installedSha);
                    $result['commits_behind'] = (int) ($comparison['ahead_by'] ?? count((array) ($comparison['commits'] ?? [])));
                    $result['commits'] = array_map(fn (array $commit) => [
                        'sha' => substr((string) ($commit['sha'] ?? ''), 0, 7),
                        'message' => \App\Core\Str::limit((string) ($commit['commit']['message'] ?? ''), 120),
                        'author' => (string) ($commit['commit']['author']['name'] ?? ''),
                        'date' => (string) ($commit['commit']['author']['date'] ?? ''),
                    ], array_slice((array) ($comparison['commits'] ?? []), -30));
                    $files = (array) ($comparison['files'] ?? []);
                    $result['files_changed'] = count($files);
                    $result['files'] = array_map(fn (array $file) => [
                        'filename' => (string) ($file['filename'] ?? ''),
                        'status' => (string) ($file['status'] ?? ''),
                        'additions' => (int) ($file['additions'] ?? 0),
                        'deletions' => (int) ($file['deletions'] ?? 0),
                    ], array_slice($files, 0, 300));
                    $result['estimated_kb'] = max(64, (int) (array_sum(array_map(
                        fn (array $file) => ((int) ($file['additions'] ?? 0) + (int) ($file['deletions'] ?? 0)) * 0.05,
                        $files
                    ))));
                } catch (\Throwable $e) {
                    Logger::channel('update')->warning('Compare failed', ['error' => $e->getMessage()]);
                    $result['compare_error'] = $e->getMessage();
                }
            }
        }

        Cache::put('update_check', $result, 900);
        set_setting('update_last_checked_at', date('c'));
        return $result;
    }

    /**
     * Daily scheduled check → dashboard banner + email + notification.
     */
    public static function checkAndNotify(): void
    {
        $result = self::check(true);
        if (empty($result['configured']) || !empty($result['up_to_date']) || !empty($result['first_install'])) {
            set_setting('update_available', '0');
            return;
        }

        $newVersion = (string) ($result['new_version'] ?? $result['latest_sha']);
        $alreadyNotified = (string) setting('update_notified_sha', '');
        set_setting('update_available', '1');
        set_setting('update_available_version', $newVersion);

        if ($alreadyNotified === $result['latest_sha']) {
            return; // already notified about this commit
        }
        set_setting('update_notified_sha', (string) $result['latest_sha']);

        DB::table('notifications')->insert([
            'tenant_id' => null,
            'user_id' => null,
            'type' => 'update.available',
            'title' => __('update.available', 'Update available: :v', ['v' => $newVersion]),
            'body' => implode("\n", array_slice((array) ($result['changelog'] ?? []), 0, 5)),
            'link' => '/admin/updates',
            'created_at' => now(),
        ]);

        $email = ((string) setting('update_notify_email', '') ?: (string) setting('alert_email', ''));
        if ($email !== '') {
            Mail::make()
                ->to($email)
                ->subject('[' . setting('app_name', 'Krishna WhatsApp Cloud') . '] Update available: ' . $newVersion)
                ->html(
                    '<p>A new version is available for one-click install.</p>'
                    . '<p><strong>' . e((string) ($result['installed_version'] ?? '')) . ' → ' . e($newVersion) . '</strong> ('
                    . e((string) ($result['commits_behind'] ?? '?')) . ' commits, '
                    . e((string) ($result['files_changed'] ?? '?')) . ' files)</p>'
                    . '<ul>' . implode('', array_map(fn ($line) => '<li>' . e((string) $line) . '</li>', array_slice((array) ($result['changelog'] ?? []), 0, 8))) . '</ul>'
                    . '<p>Install from Admin → System → Updates.</p>'
                )
                ->send();
        }

        \App\Core\Event::fire('update.available', $result);

        // Auto-update window (03:00 tenant time by default)
        if (setting('update_auto_install', '0') === '1') {
            $window = (string) setting('update_auto_window', '03');
            if ((int) date('G') === (int) $window) {
                Logger::channel('update')->info('Auto-update starting (maintenance window)');
                try {
                    UpdateInstaller::run(null);
                } catch (\Throwable $e) {
                    Logger::channel('update')->error('Auto-update failed', ['error' => $e->getMessage()]);
                }
            }
        }
    }
}
