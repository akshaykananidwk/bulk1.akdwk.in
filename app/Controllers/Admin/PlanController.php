<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\View;

final class PlanController extends Controller
{
    public const FEATURES = [
        'contacts', 'messages_monthly', 'agents', 'waba_numbers',
        'campaigns_monthly', 'ai_tokens_monthly', 'storage_mb', 'api_calls_daily', 'bot_flows',
    ];

    public function index(Request $request): never
    {
        $plans = DB::table('plans')->orderBy('sort_order')->get();
        $features = [];
        foreach (DB::table('plan_features')->get() as $row) {
            $features[(int) $row['plan_id']][(string) $row['feature']] = $row['value'];
        }

        Layout::title(__('admin.plans', 'Plans'));
        View::render('admin/plans', [
            'plans' => $plans,
            'features' => $features,
            'featureKeys' => self::FEATURES,
        ], 'layouts/admin');
    }

    public function store(Request $request): never
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:100',
            'price_monthly' => 'required|numeric',
            'price_yearly' => 'nullable|numeric',
            'trial_days' => 'required|integer|between:0,90',
        ]);

        $slug = Sanitizer::slug((string) $data['name']);
        if (DB::table('plans')->where('slug', $slug)->exists()) {
            $slug .= '-' . substr((string) time(), -4);
        }

        $planId = DB::table('plans')->insert([
            'name' => (string) $data['name'],
            'slug' => $slug,
            'description' => $request->str('description'),
            'price_monthly' => (float) $data['price_monthly'],
            'price_yearly' => (float) ($data['price_yearly'] ?? (float) $data['price_monthly'] * 10),
            'trial_days' => (int) $data['trial_days'],
            'is_active' => 1,
            'sort_order' => (int) (DB::table('plans')->max('sort_order') ?? 0) + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->saveFeatures($planId, $request);
        audit_log('admin.plan_created', 'plan', $planId);
        Redirect::to('/admin/plans')->with('success', __('admin.plan_created', 'Plan created.'))->send();
    }

    public function update(Request $request): never
    {
        $plan = DB::table('plans')->where('id', (int) $request->route('id'))->first();
        if ($plan === null) {
            Response::abort(404);
        }

        $data = $this->validate($request, [
            'name' => 'required|string|max:100',
            'price_monthly' => 'required|numeric',
            'price_yearly' => 'nullable|numeric',
            'trial_days' => 'required|integer|between:0,90',
        ]);

        DB::table('plans')->where('id', $plan['id'])->update([
            'name' => (string) $data['name'],
            'description' => $request->str('description'),
            'price_monthly' => (float) $data['price_monthly'],
            'price_yearly' => (float) ($data['price_yearly'] ?? $plan['price_yearly']),
            'trial_days' => (int) $data['trial_days'],
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'is_featured' => $request->bool('is_featured') ? 1 : 0,
            'updated_at' => now(),
        ]);

        $this->saveFeatures((int) $plan['id'], $request);
        audit_log('admin.plan_updated', 'plan', (int) $plan['id']);
        Redirect::to('/admin/plans')->with('success', __('admin.plan_updated', 'Plan updated.'))->send();
    }

    private function saveFeatures(int $planId, Request $request): void
    {
        foreach ($request->arr('features') as $feature => $value) {
            if (!in_array($feature, self::FEATURES, true)) {
                continue;
            }
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value === '') {
                $value = '-1'; // unlimited
            }
            $exists = DB::table('plan_features')->where('plan_id', $planId)->where('feature', $feature)->exists();
            if ($exists) {
                DB::table('plan_features')->where('plan_id', $planId)->where('feature', $feature)->update(['value' => $value]);
            } else {
                DB::table('plan_features')->insert(['plan_id' => $planId, 'feature' => $feature, 'value' => $value]);
            }
        }
    }
}
