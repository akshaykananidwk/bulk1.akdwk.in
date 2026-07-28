<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Queue;
use App\Core\Request;
use App\Core\Scheduler;
use App\Core\View;

final class HealthController extends Controller
{
    public function index(Request $request): never
    {
        $dbSize = DB::selectOne(
            'SELECT ROUND(SUM(data_length + index_length) / 1048576, 1) AS mb FROM information_schema.tables WHERE table_schema = ?',
            [(string) \App\Core\Config::get('db.database')]
        );
        $dbVersion = DB::selectOne('SELECT VERSION() AS v');
        $cronHeartbeat = Scheduler::lastHeartbeat();
        $extensions = ['pdo_mysql', 'curl', 'mbstring', 'openssl', 'zip', 'gd', 'fileinfo', 'json', 'bcmath', 'intl', 'redis', 'apcu'];
        $extensionStatus = [];
        foreach ($extensions as $extension) {
            $extensionStatus[$extension] = extension_loaded($extension);
        }

        Layout::title(__('admin.health', 'System Health'));
        View::render('admin/health', [
            'checks' => [
                'php_version' => PHP_VERSION,
                'db_version' => (string) ($dbVersion['v'] ?? '?'),
                'db_size_mb' => (float) ($dbSize['mb'] ?? 0),
                'disk_free' => (float) @disk_free_space(ROOT_PATH),
                'disk_total' => (float) @disk_total_space(ROOT_PATH),
                'cron_last_run' => $cronHeartbeat,
                'cron_ok' => $cronHeartbeat !== null && (time() - $cronHeartbeat) < 120,
                'queue_backlog' => Queue::size(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'maintenance' => is_file(STORAGE_PATH . '/maintenance.flag'),
                'storage_writable' => is_writable(STORAGE_PATH),
                'uploads_writable' => is_writable(UPLOAD_PATH),
                'https' => \App\Core\Request::isSecure(),
                'memory_limit' => (string) ini_get('memory_limit'),
                'upload_max' => (string) ini_get('upload_max_filesize'),
                'opcache' => function_exists('opcache_get_status') && (bool) @opcache_get_status(false),
            ],
            'extensions' => $extensionStatus,
            'appVersion' => app_version(),
            'installedVersion' => (string) setting('installed_version', ''),
        ], 'layouts/admin');
    }
}
