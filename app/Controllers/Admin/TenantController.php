<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class TenantController extends Controller
{
    public function index(Request $request): never
    {
        $page = max(1, $request->int('page', 1));
        $search = $request->str('q');

        $query = DB::table('tenants t')
            ->leftJoin('plans', 't.plan_id', '=', 'plans.id')
            ->where('t.status', '!=', 'deleted')
            ->select('t.id', 't.name', 't.slug', 't.email', 't.status', 't.trial_ends_at',
                't.subscription_ends_at', 't.created_at', 'plans.name AS plan_name');
        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->whereGroup(function ($q) use ($like) {
                $q->whereLike('t.name', $like)->whereLike('t.email', $like, 'OR');
            });
        }

        Layout::title(__('admin.tenants', 'Tenants'));
        View::render('admin/tenants/index', [
            'result' => $query->orderBy('t.id', 'DESC')->paginate($page, 30),
            'search' => $search,
        ], 'layouts/admin');
    }

    public function show(Request $request): never
    {
        $tenant = $this->findTenant($request);
        $tenantId = (int) $tenant['id'];

        Layout::title($tenant['name']);
        View::render('admin/tenants/show', [
            'workspace' => $tenant,
            'plan' => $tenant['plan_id'] ? DB::table('plans')->where('id', $tenant['plan_id'])->first() : null,
            'plans' => DB::table('plans')->where('is_active', 1)->orderBy('sort_order')->get(),
            'users' => DB::table('users')->where('tenant_id', $tenantId)->get(),
            'wallet' => DB::table('wallets')->where('tenant_id', $tenantId)->first(),
            'numbers' => DB::table('phone_numbers')->where('tenant_id', $tenantId)->get(),
            'stats' => [
                'contacts' => DB::table('contacts')->where('tenant_id', $tenantId)->count(),
                'messages_month' => DB::table('messages')->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', date('Y-m-01 00:00:00'))->count(),
                'campaigns' => DB::table('campaigns')->where('tenant_id', $tenantId)->count(),
            ],
            'transactions' => DB::table('transactions')->where('tenant_id', $tenantId)->orderBy('id', 'DESC')->limit(10)->get(),
        ], 'layouts/admin');
    }

    public function suspend(Request $request): never
    {
        $tenant = $this->findTenant($request);
        DB::table('tenants')->where('id', $tenant['id'])->update(['status' => 'suspended', 'updated_at' => now()]);
        audit_log('admin.tenant_suspended', 'tenant', (int) $tenant['id']);
        Redirect::back('/admin/tenants')->with('success', __('admin.tenant_suspended', 'Tenant suspended.'))->send();
    }

    public function activate(Request $request): never
    {
        $tenant = $this->findTenant($request);
        DB::table('tenants')->where('id', $tenant['id'])->update(['status' => 'active', 'updated_at' => now()]);
        audit_log('admin.tenant_activated', 'tenant', (int) $tenant['id']);
        Redirect::back('/admin/tenants')->with('success', __('admin.tenant_activated', 'Tenant activated.'))->send();
    }

    public function extendTrial(Request $request): never
    {
        $tenant = $this->findTenant($request);
        $days = max(1, min(365, $request->int('days', 7)));
        $base = !empty($tenant['trial_ends_at']) && strtotime((string) $tenant['trial_ends_at']) > time()
            ? strtotime((string) $tenant['trial_ends_at'])
            : time();
        DB::table('tenants')->where('id', $tenant['id'])->update([
            'trial_ends_at' => date('Y-m-d H:i:s', $base + $days * 86400),
            'updated_at' => now(),
        ]);
        audit_log('admin.trial_extended', 'tenant', (int) $tenant['id'], ['days' => $days]);
        Redirect::back('/admin/tenants/' . $tenant['id'])->with('success', __('admin.trial_extended', 'Trial extended by :n days.', ['n' => (string) $days]))->send();
    }

    public function adjustWallet(Request $request): never
    {
        $tenant = $this->findTenant($request);
        $amount = (float) $request->str('amount');
        $reason = $request->str('reason') ?: 'Admin adjustment';
        if ($amount == 0.0) {
            Redirect::back('/admin/tenants/' . $tenant['id'])->with('error', __('admin.amount_required', 'Enter a non-zero amount.'))->send();
        }

        DB::transaction(function () use ($tenant, $amount, $reason) {
            $wallet = DB::table('wallets')->where('tenant_id', $tenant['id'])->lockForUpdate()->first();
            if ($wallet === null) {
                DB::table('wallets')->insert([
                    'tenant_id' => (int) $tenant['id'],
                    'balance' => max(0, $amount),
                    'currency' => 'INR',
                    'updated_at' => now(),
                ]);
                $newBalance = max(0, $amount);
            } else {
                $newBalance = (float) $wallet['balance'] + $amount;
                DB::table('wallets')->where('id', $wallet['id'])->update(['balance' => $newBalance, 'updated_at' => now()]);
            }
            DB::table('wallet_transactions')->insert([
                'tenant_id' => (int) $tenant['id'],
                'type' => $amount > 0 ? 'credit' : 'debit',
                'amount' => abs($amount),
                'balance_after' => $newBalance,
                'reason' => mb_substr($reason, 0, 191),
                'reference_type' => 'admin_adjustment',
                'reference_id' => Auth::id(),
                'created_at' => now(),
            ]);
        });

        audit_log('admin.wallet_adjusted', 'tenant', (int) $tenant['id'], ['amount' => $amount]);
        Redirect::back('/admin/tenants/' . $tenant['id'])->with('success', __('admin.wallet_adjusted', 'Wallet adjusted.'))->send();
    }

    public function changePlan(Request $request): never
    {
        $tenant = $this->findTenant($request);
        $planId = $request->int('plan_id');
        $plan = DB::table('plans')->where('id', $planId)->first();
        if ($plan === null) {
            Redirect::back('/admin/tenants/' . $tenant['id'])->with('error', __('admin.plan_invalid', 'Invalid plan.'))->send();
        }
        DB::table('tenants')->where('id', $tenant['id'])->update(['plan_id' => $planId, 'updated_at' => now()]);
        audit_log('admin.plan_changed', 'tenant', (int) $tenant['id'], ['plan' => $plan['slug']]);
        Redirect::back('/admin/tenants/' . $tenant['id'])->with('success', __('admin.plan_changed', 'Plan changed to :p.', ['p' => (string) $plan['name']]))->send();
    }

    public function impersonate(Request $request): never
    {
        $tenant = $this->findTenant($request);
        $owner = DB::table('users')->where('tenant_id', $tenant['id'])->orderBy('id', 'ASC')->first();
        if ($owner === null) {
            Redirect::back('/admin/tenants')->with('error', __('admin.no_users', 'This tenant has no users.'))->send();
        }
        if (Auth::impersonate((int) $owner['id'])) {
            audit_log('admin.impersonation_started', 'user', (int) $owner['id'], ['tenant_id' => $tenant['id']]);
            Redirect::to('/tenant')->send();
        }
        Redirect::back('/admin/tenants')->with('error', __('admin.impersonate_failed', 'Impersonation failed.'))->send();
    }

    private function findTenant(Request $request): array
    {
        $tenant = DB::table('tenants')->where('id', (int) $request->route('id'))->first();
        if ($tenant === null) {
            Response::abort(404);
        }
        return $tenant;
    }
}
