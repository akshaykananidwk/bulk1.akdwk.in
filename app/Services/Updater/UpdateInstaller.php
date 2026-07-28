<?php

declare(strict_types=1);

namespace App\Services\Updater;

use App\Core\Cache;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Mail;
use App\Core\Migrator;

/**
 * "Update Now" — the 11-step one-click GitHub update (§12.3) with
 * automatic rollback (§12.4). Progress is written to a JSON file that
 * the admin UI streams via SSE.
 */
final class UpdateInstaller
{
    private const PROGRESS_FILE = '/tmp/update_progress.json';
    private const LOCK_STALE_SECONDS = 1800;

    /**
     * Run the full update. Throws on abort (preflight) — after step 5,
     * failures trigger rollback automatically.
     */
    public static function run(?int $userId): array
    {
        $startedAt = time();
        $lockPath = STORAGE_PATH . '/locks/update.lock';

        // [1/11] Preflight ----------------------------------------------------
        self::progress(1, 'Preflight checks', 'running');

        if (is_file($lockPath) && (time() - (int) @filemtime($lockPath)) < self::LOCK_STALE_SECONDS) {
            throw new \RuntimeException('Another update is already running.');
        }
        @mkdir(dirname($lockPath), 0755, true);
        @file_put_contents($lockPath, (string) getmypid());

        try {
            if (!class_exists(\ZipArchive::class)) {
                throw new \RuntimeException('PHP zip extension is required.');
            }
            $client = new GithubClient();
            if (!$client->configured()) {
                throw new \RuntimeException('GitHub repository is not configured.');
            }

            $check = UpdateChecker::check(true);
            if (!empty($check['up_to_date'])) {
                throw new \RuntimeException('Already up to date.');
            }
            $targetSha = (string) ($check['latest_sha'] ?? '');
            $newVersion = (string) ($check['new_version'] ?? $targetSha);
            $minPhp = (string) ($check['min_php'] ?? '8.3.0');
            if (version_compare(PHP_VERSION, $minPhp, '<')) {
                throw new \RuntimeException('This update requires PHP ≥ ' . $minPhp . ' (you have ' . PHP_VERSION . ').');
            }
            foreach ((array) ($check['required_ext'] ?? []) as $extension) {
                if (!extension_loaded((string) $extension)) {
                    throw new \RuntimeException('Missing required PHP extension: ' . $extension);
                }
            }
            if (!is_writable(ROOT_PATH)) {
                throw new \RuntimeException('Root directory is not writable.');
            }
            $estimatedBytes = ((int) ($check['estimated_kb'] ?? 10240)) * 1024;
            $free = (float) @disk_free_space(STORAGE_PATH);
            if ($free > 0 && $free < max($estimatedBytes * 3, 200 * 1048576)) {
                throw new \RuntimeException('Insufficient free disk space for a safe update (need ≥ ' . \App\Core\Str::humanBytes(max($estimatedBytes * 3, 200 * 1048576)) . ').');
            }
            DB::selectOne('SELECT 1 AS ok'); // DB healthy
        } catch (\Throwable $e) {
            @unlink($lockPath);
            self::progress(1, 'Preflight failed: ' . $e->getMessage(), 'failed');
            throw $e;
        }

        $fromVersion = (string) setting('installed_version', app_version());
        $filesBackupPath = null;
        $dbBackupPath = null;
        $migrationsRun = [];
        $historyId = null;

        try {
            // [2/11] Maintenance mode -----------------------------------------
            self::progress(2, 'Enabling maintenance mode', 'running');
            $adminIp = PHP_SAPI === 'cli' ? '127.0.0.1' : \App\Core\Request::instance()->ip();
            file_put_contents(STORAGE_PATH . '/maintenance.flag', json_encode([
                'started_at' => date('c'),
                'allowed_ips' => array_values(array_unique([$adminIp, '127.0.0.1'])),
            ]));

            // [3/11] File backup ------------------------------------------------
            self::progress(3, 'Backing up files', 'running');
            [$filesBackupPath] = BackupService::filesBackup('pre_update');

            // [4/11] Database backup --------------------------------------------
            self::progress(4, 'Backing up database', 'running');
            [$dbBackupPath] = BackupService::databaseBackup('pre_update');

            // [5/11] Download ----------------------------------------------------
            self::progress(5, 'Downloading package from GitHub', 'running');
            $tmpDir = STORAGE_PATH . '/tmp';
            if (!is_dir($tmpDir)) {
                @mkdir($tmpDir, 0755, true);
            }
            $zipPath = $tmpDir . '/update_' . $startedAt . '.zip';
            $client->downloadZipball($targetSha !== '' ? $targetSha : $client->branch(), $zipPath, function (int $now, int $total) {
                if ($total > 0) {
                    self::progress(5, 'Downloading package… ' . \App\Core\Str::humanBytes($now) . ' / ' . \App\Core\Str::humanBytes($total), 'running');
                }
            });

            // [6/11] Verify & extract (ZIP-SLIP protected) -----------------------
            self::progress(6, 'Verifying & extracting package', 'running');
            $extractDir = $tmpDir . '/update_' . $startedAt;
            $packageRoot = self::verifyAndExtract($zipPath, $extractDir);

            // [7/11] Copy files ---------------------------------------------------
            self::progress(7, 'Copying files (protected paths skipped)', 'running');
            $manifest = self::readManifest($packageRoot);
            $copied = self::copyFiles($packageRoot, $manifest);

            // [8/11] Migrations ----------------------------------------------------
            self::progress(8, 'Running database migrations', 'running');
            $migrationsRun = Migrator::run();
            foreach ((array) ($manifest['post_update_sql'] ?? []) as $sql) {
                if (is_string($sql) && trim($sql) !== '') {
                    Migrator::runSqlBatch($sql);
                }
            }

            // [9/11] Clear cache ----------------------------------------------------
            self::progress(9, 'Clearing cache & recompiling', 'running');
            Cache::flush();
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            set_setting('assets_version', (string) time());

            // [10/11] Finalize -------------------------------------------------------
            self::progress(10, 'Finalizing & version bump', 'running');
            set_setting('installed_commit_sha', $targetSha);
            set_setting('installed_version', $newVersion);
            set_setting('installed_build', (string) ($check['new_build'] ?? ''));
            set_setting('update_available', '0');
            Cache::forget('update_check');

            $historyId = DB::table('update_history')->insert([
                'from_version' => $fromVersion,
                'to_version' => $newVersion,
                'commit_sha' => $targetSha,
                'commit_message' => \App\Core\Str::limit((string) ($check['latest_commit_message'] ?? ''), 1000),
                'files_changed' => $copied,
                'migrations_run' => count($migrationsRun),
                'duration_seconds' => time() - $startedAt,
                'status' => 'success',
                'backup_file_path' => $filesBackupPath,
                'backup_db_path' => $dbBackupPath,
                'triggered_by' => $userId,
                'created_at' => now(),
            ]);

            // Cleanup temp + prune old backups
            @unlink($zipPath);
            self::deleteDirectory($extractDir);
            BackupService::prune((int) setting('update_keep_backups', '5'));

            $notifyEmail = (string) setting('update_notify_email', setting('alert_email', ''));
            if ($notifyEmail !== '') {
                Mail::make()->to($notifyEmail)
                    ->subject('[' . setting('app_name', 'Krishna WhatsApp Cloud') . '] Updated to ' . $newVersion)
                    ->text("Update completed successfully.\n\n{$fromVersion} → {$newVersion}\nCommit: {$targetSha}\nFiles copied: {$copied}\nMigrations: " . count($migrationsRun) . "\nDuration: " . (time() - $startedAt) . 's')
                    ->send();
            }
            Logger::channel('update')->info('Update completed', ['to' => $newVersion, 'sha' => $targetSha, 'files' => $copied]);

            // [11/11] Maintenance off ---------------------------------------------
            self::progress(11, 'Disabling maintenance mode', 'running');
            @unlink(STORAGE_PATH . '/maintenance.flag');
            @unlink($lockPath);
            self::progress(11, 'Update completed: ' . $fromVersion . ' → ' . $newVersion, 'success');

            return ['success' => true, 'from' => $fromVersion, 'to' => $newVersion, 'history_id' => $historyId];
        } catch (\Throwable $e) {
            Logger::channel('update')->error('Update failed — rolling back', ['error' => Logger::redact($e->getMessage())]);
            self::rollback($e, $filesBackupPath, $dbBackupPath, $fromVersion, $userId, $startedAt);
            @unlink($lockPath);
            throw new \RuntimeException('Update failed and was rolled back: ' . $e->getMessage(), 0, $e);
        }
    }

    // -- Step R: auto-rollback ---------------------------------------------------

    private static function rollback(\Throwable $cause, ?string $filesBackup, ?string $dbBackup, string $fromVersion, ?int $userId, int $startedAt): void
    {
        try {
            if ($filesBackup !== null && is_file($filesBackup)) {
                self::progress('R1', 'Restoring files from backup', 'rollback');
                // Remove files the failed update ADDED first — restoring the
                // backup only overwrites, it cannot restore "absence".
                $addedListFile = STORAGE_PATH . '/tmp/update_added_files.json';
                if (is_file($addedListFile)) {
                    $rootReal = realpath(ROOT_PATH);
                    foreach ((array) json_decode((string) file_get_contents($addedListFile), true) as $relative) {
                        if (!is_string($relative) || $relative === '' || str_contains($relative, '..')) {
                            continue;
                        }
                        $target = ($rootReal ?: ROOT_PATH) . '/' . $relative;
                        if (is_file($target)) {
                            @unlink($target);
                        }
                    }
                    @unlink($addedListFile);
                }
                BackupService::restoreFiles($filesBackup);
            }
            if ($dbBackup !== null && is_file($dbBackup)) {
                self::progress('R2', 'Restoring database from backup', 'rollback');
                BackupService::restoreDatabase($dbBackup);
            }

            self::progress('R3', 'Clearing cache', 'rollback');
            Cache::flush();
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            self::progress('R4', 'Recording failure', 'rollback');
            try {
                DB::table('update_history')->insert([
                    'from_version' => $fromVersion,
                    'to_version' => $fromVersion,
                    'commit_sha' => null,
                    'commit_message' => null,
                    'files_changed' => 0,
                    'migrations_run' => 0,
                    'duration_seconds' => time() - $startedAt,
                    'status' => 'rolled_back',
                    'error' => Logger::redact(\App\Core\Str::limit($cause->getMessage(), 2000)),
                    'backup_file_path' => $filesBackup,
                    'backup_db_path' => $dbBackup,
                    'triggered_by' => $userId,
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
                // history table may be mid-restore — the log file still has it
            }

            self::progress('R5', 'Disabling maintenance mode', 'rollback');
            @unlink(STORAGE_PATH . '/maintenance.flag');

            self::progress('R6', 'Update failed — system restored to previous version', 'rolled_back');

            $email = (string) setting('update_notify_email', setting('alert_email', ''));
            if ($email !== '') {
                Mail::make()->to($email)
                    ->subject('[' . setting('app_name', 'Krishna WhatsApp Cloud') . '] Update FAILED — rolled back automatically')
                    ->text("The update failed and the system was automatically restored.\n\nError:\n"
                        . Logger::redact($cause->getMessage())
                        . "\n\nBackups used:\nFiles: " . ($filesBackup ?? 'n/a') . "\nDatabase: " . ($dbBackup ?? 'n/a'))
                    ->send();
            }
        } catch (\Throwable $rollbackError) {
            // Rollback itself failed: keep maintenance ON, give manual recovery info
            Logger::channel('update')->error('ROLLBACK FAILED — manual recovery required', [
                'rollback_error' => $rollbackError->getMessage(),
                'original_error' => $cause->getMessage(),
                'files_backup' => $filesBackup,
                'db_backup' => $dbBackup,
            ]);
            self::progress('R!', 'ROLLBACK FAILED — maintenance mode stays ON. Restore manually from: '
                . ($filesBackup ?? 'n/a') . ' and ' . ($dbBackup ?? 'n/a')
                . '. See storage/logs/update-*.log', 'fatal');

            $email = (string) setting('update_notify_email', setting('alert_email', ''));
            if ($email !== '') {
                Mail::make()->to($email)
                    ->subject('[URGENT] Update rollback FAILED — manual recovery required')
                    ->text("Both the update and the automatic rollback failed. The site remains in maintenance mode.\n\n"
                        . "Restore manually:\n1. Unzip {$filesBackup} over the site root\n"
                        . "2. Import {$dbBackup} (gunzip first)\n3. Delete storage/maintenance.flag\n\n"
                        . 'Original error: ' . Logger::redact($cause->getMessage())
                        . "\nRollback error: " . Logger::redact($rollbackError->getMessage()))
                    ->send();
            }
        }
    }

    /**
     * Manual rollback to a retained backup pair (update history page).
     */
    public static function manualRollback(int $historyId): void
    {
        $history = DB::table('update_history')->where('id', $historyId)->first();
        if ($history === null || empty($history['backup_file_path'])) {
            throw new \RuntimeException('No backup retained for this update.');
        }
        if (!is_file((string) $history['backup_file_path'])) {
            throw new \RuntimeException('Backup file no longer exists: ' . basename((string) $history['backup_file_path']));
        }

        file_put_contents(STORAGE_PATH . '/maintenance.flag', json_encode([
            'started_at' => date('c'),
            'allowed_ips' => [PHP_SAPI === 'cli' ? '127.0.0.1' : \App\Core\Request::instance()->ip(), '127.0.0.1'],
        ]));
        try {
            BackupService::restoreFiles((string) $history['backup_file_path']);
            if (!empty($history['backup_db_path']) && is_file((string) $history['backup_db_path'])) {
                BackupService::restoreDatabase((string) $history['backup_db_path']);
            }
            Cache::flush();
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            set_setting('installed_version', (string) $history['from_version']);
            set_setting('installed_commit_sha', '');
            DB::table('update_history')->where('id', $historyId)->update(['status' => 'rolled_back']);
            audit_log('update.manual_rollback', 'update_history', $historyId);
        } finally {
            @unlink(STORAGE_PATH . '/maintenance.flag');
        }
    }

    // -- Package handling ----------------------------------------------------------

    /**
     * Verify the zipball and extract with full zip-slip protection.
     * Returns the package root (GitHub wraps in {owner}-{repo}-{sha}/).
     */
    public static function verifyAndExtract(string $zipPath, string $extractDir): string
    {
        if (!is_file($zipPath) || (int) filesize($zipPath) < 1024) {
            throw new \RuntimeException('Downloaded package is missing or empty.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Downloaded package is not a valid zip.');
        }

        if (!is_dir($extractDir) && !@mkdir($extractDir, 0755, true)) {
            throw new \RuntimeException('Cannot create extraction directory.');
        }
        $extractReal = realpath($extractDir);
        if ($extractReal === false) {
            throw new \RuntimeException('Cannot resolve extraction directory.');
        }

        $hasVersionJson = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            // 🔒 ZIP-SLIP PROTECTION: reject traversal, absolute paths, drive letters
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '\\') || preg_match('/^[A-Za-z]:/', $name)) {
                $zip->close();
                self::deleteDirectory($extractDir);
                throw new \RuntimeException('SECURITY: package contains an illegal path (' . $name . '). Update aborted.');
            }
            // Reject symlink entries (unix external attribute mode 0xA000)
            $opsys = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                && $opsys === \ZipArchive::OPSYS_UNIX
                && ((($attributes >> 16) & 0xF000) === 0xA000)) {
                $zip->close();
                self::deleteDirectory($extractDir);
                throw new \RuntimeException('SECURITY: package contains a symlink (' . $name . '). Update aborted.');
            }
            if (str_ends_with($name, 'version.json')) {
                $hasVersionJson = true;
            }

            // Manual extraction with normalised target verification
            $target = $extractReal . '/' . $name;
            $normalised = self::normalisePath($target);
            if (!str_starts_with($normalised, $extractReal . '/')) {
                $zip->close();
                self::deleteDirectory($extractDir);
                throw new \RuntimeException('SECURITY: package entry escapes the extraction directory. Update aborted.');
            }

            if (str_ends_with($name, '/')) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
                continue;
            }
            $directory = dirname($target);
            if (!is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                $zip->close();
                self::deleteDirectory($extractDir);
                throw new \RuntimeException('Package extraction failed at ' . $name);
            }
            file_put_contents($target, $contents);
        }
        $zip->close();

        if (!$hasVersionJson) {
            self::deleteDirectory($extractDir);
            throw new \RuntimeException('Package is missing version.json — not a valid Krishna WhatsApp Cloud release.');
        }

        // Auto-detect GitHub's wrapper folder
        $entries = array_values(array_filter(scandir($extractReal) ?: [], fn ($entry) => $entry !== '.' && $entry !== '..'));
        if (count($entries) === 1 && is_dir($extractReal . '/' . $entries[0])) {
            return $extractReal . '/' . $entries[0];
        }
        return $extractReal;
    }

    private static function readManifest(string $packageRoot): array
    {
        $manifest = [];
        $file = $packageRoot . '/update.json';
        if (is_file($file)) {
            $manifest = json_decode((string) file_get_contents($file), true) ?: [];
        }
        return $manifest;
    }

    /**
     * Copy package files over the live install, honouring protected paths,
     * deleting update.json.delete[] entries, atomic per-file writes,
     * skipping identical files (SHA-1).
     */
    public static function copyFiles(string $packageRoot, array $manifest): int
    {
        $protected = array_merge(
            (array) (require CONFIG_PATH . '/protected_paths.php'),
            array_map('strval', (array) ($manifest['protect'] ?? []))
        );

        // .htaccess special-case: update it only if the local copy is unmodified
        $localHtaccess = ROOT_PATH . '/.htaccess';
        $packageHtaccess = $packageRoot . '/.htaccess';
        $htaccessProtected = false;
        if (is_file($localHtaccess) && is_file($packageHtaccess)) {
            // If admin modified .htaccess (differs from shipped) → protect it
            $installedShipped = ROOT_PATH . '/storage/.htaccess_shipped_hash';
            $shippedHash = is_file($installedShipped) ? trim((string) file_get_contents($installedShipped)) : null;
            $localHash = sha1_file($localHtaccess) ?: '';
            if ($shippedHash !== null && $shippedHash !== $localHash) {
                $htaccessProtected = true;
            }
        }

        $isProtected = function (string $relative) use ($protected, $htaccessProtected): bool {
            if ($relative === '.htaccess' && $htaccessProtected) {
                return true;
            }
            foreach ($protected as $path) {
                $path = trim((string) $path);
                if ($path === '') {
                    continue;
                }
                if (str_ends_with($path, '/')) {
                    if (str_starts_with($relative . '/', $path) || str_starts_with($relative, rtrim($path, '/') . '/')) {
                        return true;
                    }
                } elseif ($relative === $path) {
                    return true;
                }
            }
            return false;
        };

        $rootReal = realpath(ROOT_PATH);
        if ($rootReal === false) {
            throw new \RuntimeException('Cannot resolve root path.');
        }

        $copied = 0;
        $addedFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($packageRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($packageRoot) + 1));
            if ($relative === '' || $isProtected($relative)) {
                continue;
            }
            $target = $rootReal . '/' . $relative;

            if ($file->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
                continue;
            }
            if (!$file->isFile()) {
                continue; // never copy links/specials
            }

            // Skip identical files (SHA-1) — faster and lower risk
            if (is_file($target) && sha1_file($target) === sha1_file($file->getPathname())) {
                continue;
            }
            if (!is_file($target)) {
                // Brand-new file: recorded so a rollback can remove it again
                // (the pre-update backup cannot restore "absence").
                $addedFiles[] = $relative;
            }

            $directory = dirname($target);
            if (!is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }
            // Atomic per-file: write .kwctmp then rename — a partially-written
            // PHP file can never be executed.
            if (@copy($file->getPathname(), $target . '.kwctmp') === false) {
                throw new \RuntimeException('Cannot write ' . $relative);
            }
            if (@rename($target . '.kwctmp', $target) === false) {
                @unlink($target . '.kwctmp');
                throw new \RuntimeException('Cannot replace ' . $relative);
            }
            @chmod($target, 0644);
            $copied++;
        }

        // Record shipped .htaccess hash for future modified-detection
        if (is_file($packageHtaccess)) {
            @file_put_contents(ROOT_PATH . '/storage/.htaccess_shipped_hash', sha1_file($packageHtaccess));
        }

        // Persist the added-files list for rollback cleanup
        @file_put_contents(
            STORAGE_PATH . '/tmp/update_added_files.json',
            json_encode($addedFiles, JSON_UNESCAPED_UNICODE)
        );

        // Deletions from the manifest (same traversal guard)
        foreach ((array) ($manifest['delete'] ?? []) as $relative) {
            $relative = str_replace('\\', '/', (string) $relative);
            if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || $isProtected($relative)) {
                continue;
            }
            $target = $rootReal . '/' . $relative;
            $normalised = self::normalisePath($target);
            if (!str_starts_with($normalised, $rootReal . '/')) {
                continue;
            }
            if (is_file($target)) {
                @unlink($target);
            } elseif (is_dir($target)) {
                self::deleteDirectory($target);
            }
        }

        return $copied;
    }

    // -- Progress + helpers ----------------------------------------------------------

    /**
     * Write a progress event consumed by the admin SSE stream.
     */
    public static function progress(int|string $step, string $message, string $state): void
    {
        $file = STORAGE_PATH . self::PROGRESS_FILE;
        $log = [];
        if (is_file($file)) {
            $log = json_decode((string) file_get_contents($file), true) ?: [];
        }
        $log['events'] = array_slice(array_merge((array) ($log['events'] ?? []), [[
            'step' => $step,
            'message' => Logger::redact($message),
            'state' => $state,
            'at' => date('H:i:s'),
        ]]), -100);
        $log['state'] = $state;
        $log['updated_at'] = time();
        @file_put_contents($file, json_encode($log, JSON_UNESCAPED_UNICODE), LOCK_EX);
        Logger::channel('update')->info('[' . $step . '] ' . $message);
    }

    public static function progressState(): array
    {
        $file = STORAGE_PATH . self::PROGRESS_FILE;
        if (!is_file($file)) {
            return ['events' => [], 'state' => 'idle'];
        }
        return json_decode((string) file_get_contents($file), true) ?: ['events' => [], 'state' => 'idle'];
    }

    public static function resetProgress(): void
    {
        @unlink(STORAGE_PATH . self::PROGRESS_FILE);
    }

    /**
     * Normalise a path lexically (no filesystem access — target may not exist).
     */
    public static function normalisePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }
        return '/' . implode('/', $parts);
    }

    public static function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir() && !$file->isLink()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($directory);
    }
}
