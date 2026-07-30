<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Tenant;
use App\Core\View;

final class SettingsController extends Controller
{
    public function index(Request $request): never
    {
        $tenant = Tenant::current();
        Layout::title(__('nav.settings', 'Settings'));
        View::render('tenant/settings', [
            'workspace' => $tenant,
            'settings' => [
                'away_message' => (string) (Tenant::setting('away_message') ?? ''),
                'greeting_message' => (string) (Tenant::setting('greeting_message') ?? ''),
                'business_hours' => (string) (Tenant::setting('business_hours') ?? ''),
                'auto_close_hours' => (string) (Tenant::setting('auto_close_hours') ?? '48'),
                'frequency_cap_count' => (string) (Tenant::setting('frequency_cap_count') ?? '0'),
                'frequency_cap_days' => (string) (Tenant::setting('frequency_cap_days') ?? '7'),
                'humanize_typing' => (string) Tenant::setting('humanize_typing', '1'),
                'humanize_typing_seconds' => (string) Tenant::setting('humanize_typing_seconds', '10'),
                'campaign_gap_min' => (string) Tenant::setting('campaign_gap_min', '8'),
                'campaign_gap_max' => (string) Tenant::setting('campaign_gap_max', '20'),
                'campaign_daily_cap' => (string) Tenant::setting('campaign_daily_cap', '0'),
                'campaign_warmup' => (string) Tenant::setting('campaign_warmup', '1'),
                'campaign_quiet_hours' => (string) Tenant::setting('campaign_quiet_hours', ''),
            ],
        ], 'layouts/tenant');
    }

    public function update(Request $request): never
    {
        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:191',
            'timezone' => 'nullable|timezone',
            'gstin' => 'nullable|string|max:20',
            'billing_name' => 'nullable|string|max:191',
            'billing_address' => 'nullable|string|max:1000',
            'auto_close_hours' => 'nullable|integer|between:0,720',
            'frequency_cap_count' => 'nullable|integer|between:0,100',
            'frequency_cap_days' => 'nullable|integer|between:1,90',
            'humanize_typing_seconds' => 'nullable|integer|between:3,25',
            'campaign_gap_min' => 'nullable|integer|between:0,120',
            'campaign_gap_max' => 'nullable|integer|between:0,300',
            'campaign_daily_cap' => 'nullable|integer|between:0,100000',
            'campaign_quiet_hours' => 'nullable|string|max:20',
        ]);

        DB::table('tenants')->where('id', Tenant::id())->update([
            'name' => (string) $data['name'],
            'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
            'gstin' => $data['gstin'] ?? null,
            'billing_name' => $data['billing_name'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'updated_at' => now(),
        ]);

        foreach (['away_message', 'greeting_message', 'business_hours'] as $key) {
            Tenant::setSetting($key, $request->str($key));
        }
        foreach (['auto_close_hours', 'frequency_cap_count', 'frequency_cap_days', 'humanize_typing_seconds', 'campaign_gap_min', 'campaign_gap_max', 'campaign_daily_cap'] as $key) {
            if (isset($data[$key])) {
                Tenant::setSetting($key, (string) $data[$key]);
            }
        }

        // Anti-block toggles + quiet hours ("21:00-09:00" or blank = off)
        foreach (['humanize_typing', 'campaign_warmup'] as $key) {
            if ($request->input($key) !== null) {
                Tenant::setSetting($key, $request->bool($key) ? '1' : '0');
            }
        }
        $quiet = trim($request->str('campaign_quiet_hours'));
        if ($quiet === '' || preg_match('/^\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2}$/', $quiet)) {
            Tenant::setSetting('campaign_quiet_hours', $quiet);
        }

        audit_log('settings.updated');
        Redirect::to('/tenant/settings')->with('success', __('settings.saved', 'Settings saved.'))->send();
    }
}
