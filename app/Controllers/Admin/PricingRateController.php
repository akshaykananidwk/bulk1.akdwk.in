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

/**
 * Admin-editable Meta message rate card. Never hardcoded — the admin
 * updates rows when Meta revises pricing.
 */
final class PricingRateController extends Controller
{
    public function index(Request $request): never
    {
        Layout::title(__('admin.pricing', 'Pricing Rates'));
        View::render('admin/pricing-rates', [
            'rates' => DB::table('pricing_rates')->orderBy('country_code')->orderBy('category')->orderBy('effective_from', 'DESC')->get(),
        ], 'layouts/admin');
    }

    public function store(Request $request): never
    {
        $data = $this->validate($request, [
            'country_code' => 'required|string|max:5',
            'category' => 'required|in:marketing,utility,authentication,service',
            'rate' => 'required|numeric',
            'currency' => 'required|in:INR,USD,EUR,GBP,AED',
            'effective_from' => 'required|date',
        ]);

        $countryCode = strtoupper((string) $data['country_code']);
        if ($countryCode !== '*' && !preg_match('/^[A-Z]{2}$/', $countryCode)) {
            Redirect::back('/admin/pricing-rates')->with('error', __('admin.country_invalid', 'Country must be a 2-letter code or * for default.'))->send();
        }

        try {
            DB::table('pricing_rates')->insert([
                'country_code' => $countryCode,
                'country_name' => $request->str('country_name') ?: null,
                'category' => (string) $data['category'],
                'rate' => (float) $data['rate'],
                'currency' => (string) $data['currency'],
                'effective_from' => date('Y-m-d', strtotime((string) $data['effective_from'])),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            Redirect::back('/admin/pricing-rates')->with('error', __('admin.rate_duplicate', 'A rate for this country + category + date already exists.'))->send();
        }

        audit_log('admin.pricing_rate_added');
        Redirect::to('/admin/pricing-rates')->with('success', __('admin.rate_saved', 'Rate saved.'))->send();
    }

    public function destroy(Request $request): never
    {
        $rate = DB::table('pricing_rates')->where('id', (int) $request->route('id'))->first();
        if ($rate === null) {
            Response::abort(404);
        }
        DB::table('pricing_rates')->where('id', $rate['id'])->delete();
        audit_log('admin.pricing_rate_deleted');
        Redirect::to('/admin/pricing-rates')->with('success', __('admin.rate_deleted', 'Rate deleted.'))->send();
    }
}
