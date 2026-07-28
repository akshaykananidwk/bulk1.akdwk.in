<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Request;
use App\Core\Tenant;
use App\Core\View;

final class BillingController extends Controller
{
    public function index(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        $tenant = Tenant::current() ?? [];
        $plan = !empty($tenant['plan_id']) ? DB::table('plans')->where('id', $tenant['plan_id'])->first() : null;

        // Usage vs plan limits
        $usage = [];
        if ($plan !== null) {
            $features = DB::table('plan_features')->where('plan_id', $plan['id'])->get();
            foreach ($features as $feature) {
                $limit = $feature['value'] === null || $feature['value'] === '-1' ? null : (int) $feature['value'];
                $used = match ($feature['feature']) {
                    'contacts' => DB::table('contacts')->where('tenant_id', $tenantId)->count(),
                    'agents' => DB::table('users')->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                    'waba_numbers' => DB::table('phone_numbers')->where('tenant_id', $tenantId)->where('status', 'active')->count(),
                    'bot_flows' => DB::table('flows')->where('tenant_id', $tenantId)->count(),
                    default => Tenant::usage((string) $feature['feature']),
                };
                $usage[] = ['feature' => $feature['feature'], 'limit' => $limit, 'used' => $used];
            }
        }

        Layout::title(__('nav.billing', 'Billing'));
        View::render('tenant/billing', [
            'workspace' => $tenant,
            'plan' => $plan,
            'plans' => DB::table('plans')->where('is_active', 1)->orderBy('sort_order')->get(),
            'usage' => $usage,
            'wallet' => DB::table('wallets')->where('tenant_id', $tenantId)->first(),
            'invoices' => DB::table('invoices')->where('tenant_id', $tenantId)->orderBy('id', 'DESC')->limit(20)->get(),
            'subscription' => DB::table('subscriptions')->where('tenant_id', $tenantId)->orderBy('id', 'DESC')->first(),
        ], 'layouts/tenant');
    }
}
