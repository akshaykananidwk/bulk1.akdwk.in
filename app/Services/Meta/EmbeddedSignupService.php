<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Core\Crypt;
use App\Core\DB;
use App\Core\Http;
use App\Core\Logger;

/**
 * Embedded Signup completion + manual WABA connection (§5.1).
 */
final class EmbeddedSignupService
{
    /**
     * Complete the embedded-signup flow:
     * exchange code → business token → subscribe app → register phone → sync numbers.
     */
    public static function complete(int $tenantId, string $code, string $wabaId, string $phoneNumberId): array
    {
        $app = self::defaultApp();
        if ($app === null) {
            throw new \RuntimeException(__('whatsapp.no_meta_app', 'The platform Meta App is not configured. Ask your administrator.'));
        }
        $appSecret = Crypt::decrypt((string) $app['app_secret_encrypted']);
        if ($appSecret === null) {
            throw new \RuntimeException('Meta app secret cannot be decrypted.');
        }

        // 1. Exchange the returned code for a business access token
        $graphUrl = (string) config('meta.graph_url', 'https://graph.facebook.com');
        $apiVersion = (string) ($app['api_version'] ?: 'v21.0');
        $response = Http::make()
            ->timeout(30)->retry(2, [2, 8])->logTo('meta')
            ->get($graphUrl . '/' . $apiVersion . '/oauth/access_token', [
                'client_id' => (string) $app['app_id'],
                'client_secret' => $appSecret,
                'code' => $code,
            ]);
        $data = $response->json();
        $token = (string) ($data['access_token'] ?? '');
        if ($token === '') {
            $message = (string) ($data['error']['message'] ?? 'Token exchange failed');
            Logger::channel('meta')->error('Embedded signup token exchange failed', ['error' => $message]);
            throw new \RuntimeException(__('whatsapp.token_exchange_failed', 'Could not complete WhatsApp connection: ') . $message);
        }

        return self::connect($tenantId, $wabaId, $phoneNumberId, $token, 'embedded_signup', (int) $app['id']);
    }

    /**
     * Manual connection: user pastes WABA ID + Phone Number ID + permanent token.
     */
    public static function connectManual(int $tenantId, string $wabaId, string $phoneNumberId, string $token): array
    {
        $app = self::defaultApp();
        return self::connect($tenantId, $wabaId, $phoneNumberId, $token, 'permanent_system_user', $app !== null ? (int) $app['id'] : null);
    }

    private static function connect(int $tenantId, string $wabaId, string $phoneNumberId, string $token, string $mode, ?int $metaAppId): array
    {
        // Upsert the WABA account
        $existing = DB::table('waba_accounts')->where('tenant_id', $tenantId)->where('waba_id', $wabaId)->first();
        $row = [
            'meta_app_id' => $metaAppId,
            'token_mode' => $mode,
            'access_token_encrypted' => Crypt::encrypt($token),
            'status' => 'active',
            'updated_at' => now(),
        ];
        if ($existing !== null) {
            DB::table('waba_accounts')->where('id', $existing['id'])->update($row);
            $wabaRowId = (int) $existing['id'];
        } else {
            $wabaRowId = DB::table('waba_accounts')->insert($row + [
                'tenant_id' => $tenantId,
                'waba_id' => $wabaId,
                'created_at' => now(),
            ]);
        }
        $waba = DB::table('waba_accounts')->where('id', $wabaRowId)->first();
        $client = new CloudApiClient($waba);

        // 2. WABA info (name/currency)
        try {
            $info = $client->getWabaInfo();
            DB::table('waba_accounts')->where('id', $wabaRowId)->update([
                'name' => mb_substr((string) ($info['name'] ?? ''), 0, 191),
                'currency' => isset($info['currency']) ? mb_substr((string) $info['currency'], 0, 3) : null,
                'timezone_id' => isset($info['timezone_id']) ? mb_substr((string) $info['timezone_id'], 0, 10) : null,
            ]);
        } catch (MetaApiException $e) {
            Logger::channel('meta')->warning('WABA info fetch failed', ['error' => $e->getMessage()]);
        }

        // 3. Subscribe our app to the WABA's webhooks
        try {
            $client->subscribeApp();
            DB::table('waba_accounts')->where('id', $wabaRowId)->update(['subscribed_at' => now()]);
        } catch (MetaApiException $e) {
            Logger::channel('meta')->warning('subscribed_apps failed', ['error' => $e->getMessage()]);
        }

        // 4. Register the phone with a generated 6-digit PIN (idempotent-ish;
        //    already-registered numbers return an error we tolerate)
        $pin = (string) random_int(100000, 999999);
        $registered = false;
        try {
            $client->registerPhone($phoneNumberId, $pin);
            $registered = true;
        } catch (MetaApiException $e) {
            Logger::channel('meta')->info('Phone register response', ['error' => $e->getMessage()]);
        }

        // 5. Sync all phone numbers on this WABA
        $numbers = [];
        try {
            $list = $client->getPhoneNumbers();
            $numbers = (array) ($list['data'] ?? []);
        } catch (MetaApiException $e) {
            Logger::channel('meta')->warning('phone_numbers fetch failed', ['error' => $e->getMessage()]);
        }

        $hasDefault = DB::table('phone_numbers')->where('tenant_id', $tenantId)->where('is_default', 1)->exists();
        foreach ($numbers as $number) {
            $numberId = (string) ($number['id'] ?? '');
            if ($numberId === '') {
                continue;
            }
            $numberRow = [
                'display_phone_number' => (string) ($number['display_phone_number'] ?? ''),
                'verified_name' => mb_substr((string) ($number['verified_name'] ?? ''), 0, 191),
                'quality_rating' => $number['quality_rating'] ?? null,
                'code_verification_status' => $number['code_verification_status'] ?? null,
                'throughput_level' => $number['throughput']['level'] ?? null,
                'messaging_limit_tier' => $number['messaging_limit_tier'] ?? null,
                'status' => 'active',
                'updated_at' => now(),
            ];
            if ($numberId === $phoneNumberId) {
                $numberRow['is_registered'] = $registered ? 1 : 0;
                $numberRow['pin_encrypted'] = Crypt::encrypt($pin);
                if (!$hasDefault) {
                    $numberRow['is_default'] = 1;
                    $hasDefault = true;
                }
            }

            $existingNumber = DB::table('phone_numbers')->where('phone_number_id', $numberId)->first();
            if ($existingNumber !== null) {
                DB::table('phone_numbers')->where('id', $existingNumber['id'])->update($numberRow);
            } else {
                DB::table('phone_numbers')->insert($numberRow + [
                    'tenant_id' => $tenantId,
                    'waba_account_id' => $wabaRowId,
                    'phone_number_id' => $numberId,
                    'created_at' => now(),
                ]);
            }
        }

        // 6. Initial template sync (async)
        \App\Core\Queue::push(\App\Jobs\SyncTemplatesJob::class, ['waba_account_id' => $wabaRowId], 'default', 5, 0, $tenantId);

        audit_log('whatsapp.connected', 'waba_account', $wabaRowId, ['waba_id' => $wabaId, 'mode' => $mode]);

        return DB::table('waba_accounts')->where('id', $wabaRowId)->first() ?? [];
    }

    public static function defaultApp(): ?array
    {
        return DB::table('meta_apps')->orderBy('is_default', 'DESC')->orderBy('id', 'ASC')->first();
    }

    /**
     * Scheduler task: warn 7 days before token expiry.
     */
    public static function warnExpiringTokens(): void
    {
        $soon = date('Y-m-d H:i:s', time() + 7 * 86400);
        $expiring = DB::table('waba_accounts')
            ->where('status', 'active')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<', $soon)
            ->whereNull('token_expiry_warned_at')
            ->get();
        foreach ($expiring as $waba) {
            DB::table('notifications')->insert([
                'tenant_id' => (int) $waba['tenant_id'],
                'type' => 'token.expiring',
                'title' => __('whatsapp.token_expiring', 'WhatsApp token expiring soon'),
                'body' => __('whatsapp.token_expiring_body', 'Reconnect your WhatsApp account before it stops working.'),
                'link' => '/tenant/whatsapp',
                'created_at' => now(),
            ]);
            DB::table('waba_accounts')->where('id', $waba['id'])->update(['token_expiry_warned_at' => now()]);
        }
    }
}
