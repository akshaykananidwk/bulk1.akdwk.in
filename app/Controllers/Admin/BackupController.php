<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Updater\BackupService;

final class BackupController extends Controller
{
    public function index(Request $request): never
    {
        Layout::title(__('admin.backups', 'Backups'));
        View::render('admin/backups', [
            'backups' => DB::table('backups')->where('status', 'completed')->orderBy('id', 'DESC')->limit(50)->get(),
            'dailyEnabled' => setting('backup_daily', '0') === '1',
        ], 'layouts/admin');
    }

    public function createDatabase(Request $request): never
    {
        @set_time_limit(0);
        try {
            [, $size] = BackupService::databaseBackup('manual');
            audit_log('admin.backup_created', 'backup', null, ['size' => $size]);
            Redirect::to('/admin/backups')->with('success', __('admin.backup_done', 'Database backup created (:s).', ['s' => \App\Core\Str::humanBytes($size)]))->send();
        } catch (\Throwable $e) {
            Redirect::to('/admin/backups')->with('error', $e->getMessage())->send();
        }
    }

    public function download(Request $request): never
    {
        $backup = $this->findBackup($request);
        Response::download((string) $backup['path']);
    }

    public function destroy(Request $request): never
    {
        $backup = $this->findBackup($request);
        if (is_file((string) $backup['path'])) {
            @unlink((string) $backup['path']);
        }
        DB::table('backups')->where('id', $backup['id'])->update(['status' => 'deleted']);
        audit_log('admin.backup_deleted', 'backup', (int) $backup['id']);
        Redirect::to('/admin/backups')->with('success', __('admin.backup_deleted', 'Backup deleted.'))->send();
    }

    private function findBackup(Request $request): array
    {
        $backup = DB::table('backups')
            ->where('id', (int) $request->route('id'))
            ->where('status', 'completed')
            ->first();
        if ($backup === null || !is_file((string) $backup['path'])) {
            Response::abort(404);
        }
        // Path safety: must live inside storage/backups
        $real = realpath((string) $backup['path']);
        $base = realpath(STORAGE_PATH . '/backups');
        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            Response::abort(403);
        }
        return $backup;
    }
}
