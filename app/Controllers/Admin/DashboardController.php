<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Queue;
use App\Core\Request;
use App\Core\View;

final class DashboardController extends Controller
{
    public function index(Request $request): never
    {
        $monthStart = date('Y-m-01 00:00:00');

        // MRR: sum of active subscription amounts normalised to monthly
        $subs = DB::table('subscriptions')->whereIn('status', ['active', 'trialing'])->get();
        $mrr = 0.0;
        foreach ($subs as $sub) {
            $amount = (float) $sub['amount'];
            $mrr += $sub['billing_cycle'] === 'yearly' ? $amount / 12 : $amount;
        }

        $stats = [
            'tenants_total' => DB::table('tenants')->where('status', '!=', 'deleted')->count(),
            'tenants_active' => DB::table('tenants')->where('status', 'active')->count(),
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'revenue_month' => DB::table('transactions')->where('status', 'success')
                ->where('type', '!=', 'refund')->where('created_at', '>=', $monthStart)->sum('amount'),
            'messages_today' => DB::table('messages')->where('created_at', '>=', date('Y-m-d 00:00:00'))->count(),
            'queue_backlog' => Queue::size(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'open_tickets' => DB::table('support_tickets')->whereIn('status', ['open', 'customer_reply'])->count(),
        ];

        $recentTenants = DB::table('tenants')->orderBy('created_at', 'DESC')->limit(8)->get();

        // 30-day revenue chart
        $revenue = DB::table('transactions')
            ->selectRaw(DB::raw('DATE(created_at) AS d, SUM(amount) AS total'))
            ->where('status', 'success')
            ->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime('-29 days')))
            ->groupBy('d')->orderBy('d')->get();

        $heartbeat = \App\Core\Scheduler::lastHeartbeat();
        $cronStale = $heartbeat === null || (time() - $heartbeat) > 180;
        $phpBinary = PHP_BINARY !== '' && !str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : '/usr/bin/php';

        Layout::title(__('nav.dashboard', 'Dashboard'));
        View::render('admin/dashboard', [
            'stats' => $stats,
            'recentTenants' => $recentTenants,
            'revenue' => $revenue,
            'cronStale' => $cronStale,
            'cronLastRun' => $heartbeat,
            'cronCommand' => '* * * * * ' . $phpBinary . ' ' . ROOT_PATH . '/cron/scheduler.php >> /dev/null 2>&1',
            'mailLastError' => (string) setting('mail_last_error', ''),
        ], 'layouts/admin');
    }
}
