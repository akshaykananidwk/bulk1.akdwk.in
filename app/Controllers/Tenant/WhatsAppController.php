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
use App\Services\Meta\EmbeddedSignupService;

final class WhatsAppController extends Controller
{
    public function index(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        $wabas = DB::table('waba_accounts')->where('tenant_id', $tenantId)->get();
        $numbers = DB::table('phone_numbers')->where('tenant_id', $tenantId)->get();
        $app = EmbeddedSignupService::defaultApp();

        Layout::title(__('nav.whatsapp', 'WhatsApp'));
        View::render('tenant/whatsapp', [
            'wabas' => $wabas,
            'numbers' => $numbers,
            'metaApp' => $app !== null ? [
                'app_id' => $app['app_id'],
                'config_id' => $app['config_id'],
                'api_version' => $app['api_version'],
            ] : null,
            'webhookUrl' => url('/webhook/meta'),
        ], 'layouts/tenant');
    }

    /**
     * Receives the code + IDs captured client-side from FB.login /
     * WA_EMBEDDED_SIGNUP message event.
     */
    public function embeddedCallback(Request $request): never
    {
        $data = $this->validate($request, [
            'code' => 'required|string',
            'waba_id' => 'required|string|max:64',
            'phone_number_id' => 'nullable|string|max:64',
            'mode' => 'nullable|string|max:20',
        ]);
        $coexistence = ((string) ($data['mode'] ?? '')) === 'coexistence';

        // Plan limit on connected numbers
        [$allowed] = Tenant::withinLimit('waba_numbers');
        if (!$allowed) {
            $this->fail(__('billing.waba_limit', 'Your plan does not allow more WhatsApp numbers. Upgrade to add more.'), 422);
        }

        try {
            $result = EmbeddedSignupService::complete(
                (int) Tenant::id(),
                (string) $data['code'],
                (string) $data['waba_id'],
                (string) ($data['phone_number_id'] ?? ''),
                $coexistence
            );
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }

        Tenant::recordUsage('waba_numbers');
        $waba = $result['waba'];
        if ($result['generated_pin'] !== null) {
            // Shown on the reloaded WhatsApp page (the JS reloads after success)
            $_SESSION['_flash']['warning'] = self::pinNotice((string) $result['generated_pin']);
        }
        $this->ok(['waba' => ['id' => $waba['id'], 'waba_id' => $waba['waba_id']]], __('whatsapp.connected', 'WhatsApp connected successfully!'));
    }

    public function manualConnect(Request $request): never
    {
        $data = $this->validate($request, [
            'waba_id' => 'required|string|max:64',
            'phone_number_id' => 'required|string|max:64',
            'access_token' => 'required|string|min:20',
            'pin' => 'nullable|string|max:6',
        ]);
        $pin = trim((string) ($data['pin'] ?? ''));
        if ($pin !== '' && !preg_match('/^\d{6}$/', $pin)) {
            Redirect::back('/tenant/whatsapp')->with('error', __('whatsapp.pin_invalid', 'The two-step verification PIN must be exactly 6 digits.'))->send();
        }

        [$allowed] = Tenant::withinLimit('waba_numbers');
        if (!$allowed) {
            Redirect::back('/tenant/whatsapp')->with('error', __('billing.waba_limit', 'Your plan does not allow more WhatsApp numbers. Upgrade to add more.'))->send();
        }

        try {
            $result = EmbeddedSignupService::connectManual(
                (int) Tenant::id(),
                (string) $data['waba_id'],
                (string) $data['phone_number_id'],
                (string) $data['access_token'],
                $pin !== '' ? $pin : null
            );
        } catch (\Throwable $e) {
            Redirect::back('/tenant/whatsapp')->with('error', $e->getMessage())->send();
        }

        Tenant::recordUsage('waba_numbers');
        $redirect = Redirect::to('/tenant/whatsapp')->with('success', __('whatsapp.connected', 'WhatsApp connected successfully!'));
        if ($result['generated_pin'] !== null) {
            $redirect->with('warning', self::pinNotice((string) $result['generated_pin']));
        }
        $redirect->send();
    }

    private static function pinNotice(string $pin): string
    {
        return __('whatsapp.pin_generated', 'Your number was registered with a new two-step verification PIN: ') . $pin
            . __('whatsapp.pin_generated_2', ' — save it now; Meta asks for it if you ever re-register this number.');
    }

    /** Re-sync numbers + templates from Meta. */
    public function refresh(Request $request): never
    {
        $wabaId = (int) $request->route('id');
        $waba = DB::table('waba_accounts')->where('id', $wabaId)->where('tenant_id', Tenant::id())->first();
        if ($waba === null) {
            \App\Core\Response::abort(404);
        }

        try {
            $client = new \App\Services\Meta\CloudApiClient($waba);
            $list = $client->getPhoneNumbers();
            foreach ((array) ($list['data'] ?? []) as $number) {
                $numberId = (string) ($number['id'] ?? '');
                if ($numberId === '') {
                    continue;
                }
                DB::table('phone_numbers')->where('phone_number_id', $numberId)->update([
                    'display_phone_number' => (string) ($number['display_phone_number'] ?? ''),
                    'verified_name' => mb_substr((string) ($number['verified_name'] ?? ''), 0, 191),
                    'quality_rating' => $number['quality_rating'] ?? null,
                    'throughput_level' => $number['throughput']['level'] ?? null,
                    'messaging_limit_tier' => $number['messaging_limit_tier'] ?? null,
                    'updated_at' => now(),
                ]);
            }
            \App\Core\Queue::push(\App\Jobs\SyncTemplatesJob::class, ['waba_account_id' => $wabaId], 'default', 5, 0, (int) Tenant::id());

            // The token works again → clear a token/connection error (a Meta
            // account restriction stays until Meta sends a new account_update)
            $reason = (string) ($waba['status_reason'] ?? '');
            if ($waba['status'] === 'error' && !preg_match('/DISABLED_UPDATE|ACCOUNT_DELETED|ACCOUNT_RESTRICTION/', $reason)) {
                DB::table('waba_accounts')->where('id', $wabaId)->update(['status' => 'active', 'status_reason' => null, 'updated_at' => now()]);
            }
        } catch (\Throwable $e) {
            Redirect::back('/tenant/whatsapp')->with('error', $e->getMessage())->send();
        }

        Redirect::to('/tenant/whatsapp')->with('success', __('whatsapp.refreshed', 'Account refreshed. Template sync queued.'))->send();
    }

    public function disconnect(Request $request): never
    {
        $wabaId = (int) $request->route('id');
        $waba = DB::table('waba_accounts')->where('id', $wabaId)->where('tenant_id', Tenant::id())->first();
        if ($waba === null) {
            \App\Core\Response::abort(404);
        }

        DB::table('waba_accounts')->where('id', $wabaId)->update(['status' => 'disconnected', 'updated_at' => now()]);
        DB::table('phone_numbers')->where('waba_account_id', $wabaId)->update(['status' => 'inactive', 'updated_at' => now()]);
        audit_log('whatsapp.disconnected', 'waba_account', $wabaId);

        Redirect::to('/tenant/whatsapp')->with('success', __('whatsapp.disconnected', 'Account disconnected.'))->send();
    }
}
