<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Request;
use App\Core\View;

final class LogController extends Controller
{
    public function index(Request $request): never
    {
        $type = $request->str('type', 'audit');
        $page = max(1, $request->int('page', 1));

        $result = match ($type) {
            'webhook' => DB::table('webhook_logs')->orderBy('id', 'DESC')->paginate($page, 50),
            'api' => DB::table('api_logs')->orderBy('id', 'DESC')->paginate($page, 50),
            'error' => DB::table('error_logs')->orderBy('id', 'DESC')->paginate($page, 50),
            'cron' => DB::table('cron_logs')->orderBy('id', 'DESC')->paginate($page, 50),
            'login' => DB::table('login_attempts')->orderBy('id', 'DESC')->paginate($page, 50),
            default => DB::table('audit_logs')->orderBy('id', 'DESC')->paginate($page, 50),
        };

        Layout::title(__('admin.logs', 'Logs'));
        View::render('admin/logs', [
            'type' => in_array($type, ['audit', 'webhook', 'api', 'error', 'cron', 'login'], true) ? $type : 'audit',
            'result' => $result,
        ], 'layouts/admin');
    }
}
