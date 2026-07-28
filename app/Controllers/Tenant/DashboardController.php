<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Request;
use App\Core\Tenant;
use App\Core\View;

final class DashboardController extends Controller
{
    public function index(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01 00:00:00');

        $stats = [
            'contacts' => DB::table('contacts')->where('tenant_id', $tenantId)->count(),
            'messages_today' => DB::table('messages')->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $today . ' 00:00:00')->count(),
            'messages_month' => DB::table('messages')->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $monthStart)->count(),
            'open_conversations' => DB::table('conversations')->where('tenant_id', $tenantId)
                ->where('status', 'open')->where('is_archived', 0)->count(),
            'campaigns_running' => DB::table('campaigns')->where('tenant_id', $tenantId)
                ->whereIn('status', ['running', 'scheduled'])->count(),
            'cost_month' => DB::table('messages')->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $monthStart)->sum('cost'),
        ];

        // 14-day message volume for the chart
        $volume = DB::table('messages')
            ->selectRaw(DB::raw("DATE(created_at) AS d, SUM(direction = 'in') AS inbound, SUM(direction = 'out') AS outbound"))
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', date('Y-m-d 00:00:00', strtotime('-13 days')))
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $connected = DB::table('phone_numbers')->where('tenant_id', $tenantId)->where('status', 'active')->count() > 0;

        $tenant = Tenant::current();
        Layout::title(__('nav.dashboard', 'Dashboard'));

        View::render('tenant/dashboard', [
            'stats' => $stats,
            'volume' => $volume,
            'connected' => $connected,
            'trialEndsAt' => $tenant['trial_ends_at'] ?? null,
        ], 'layouts/tenant');
    }
}
