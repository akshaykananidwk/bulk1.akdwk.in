<?php

declare(strict_types=1);

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Core\Crypt;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;

/**
 * Payment gateway webhooks with signature verification + idempotency.
 * Supported: razorpay, stripe, cashfree (HMAC-based). Gateway credentials
 * are stored encrypted in settings (gateway_<name>_secret).
 */
final class PaymentWebhookController extends Controller
{
    public function receive(Request $request): never
    {
        $gateway = (string) $request->route('gateway');
        $raw = $request->rawBody();

        $verified = match ($gateway) {
            'razorpay' => $this->verifyRazorpay($request, $raw),
            'stripe' => $this->verifyStripe($request, $raw),
            'cashfree' => $this->verifyCashfree($request, $raw),
            default => false,
        };

        DB::table('webhook_logs')->insert([
            'direction' => 'in',
            'source' => 'payment_' . $gateway,
            'payload' => mb_substr($raw, 0, 65000),
            'status' => $verified ? 'received' : 'failed',
            'error' => $verified ? null : 'signature verification failed',
            'created_at' => now(),
        ]);

        if (!$verified) {
            Logger::channel('webhook')->warning('Payment webhook signature failed', ['gateway' => $gateway]);
            Response::json(['success' => false], 403);
        }

        $payload = json_decode($raw, true) ?: [];
        $this->processEvent($gateway, $payload);

        Response::json(['success' => true]);
    }

    private function processEvent(string $gateway, array $payload): void
    {
        // Normalise the three gateways into (txn_id, status, amount, currency, notes)
        [$txnId, $status, $amount, $currency, $notes] = match ($gateway) {
            'razorpay' => [
                (string) ($payload['payload']['payment']['entity']['id'] ?? ''),
                in_array($payload['event'] ?? '', ['payment.captured', 'order.paid'], true) ? 'success'
                    : (($payload['event'] ?? '') === 'payment.failed' ? 'failed' : 'pending'),
                ((float) ($payload['payload']['payment']['entity']['amount'] ?? 0)) / 100,
                strtoupper((string) ($payload['payload']['payment']['entity']['currency'] ?? 'INR')),
                (array) ($payload['payload']['payment']['entity']['notes'] ?? []),
            ],
            'stripe' => [
                (string) ($payload['data']['object']['id'] ?? ''),
                ($payload['type'] ?? '') === 'payment_intent.succeeded' ? 'success'
                    : (($payload['type'] ?? '') === 'payment_intent.payment_failed' ? 'failed' : 'pending'),
                ((float) ($payload['data']['object']['amount'] ?? 0)) / 100,
                strtoupper((string) ($payload['data']['object']['currency'] ?? 'INR')),
                (array) ($payload['data']['object']['metadata'] ?? []),
            ],
            'cashfree' => [
                (string) ($payload['data']['payment']['cf_payment_id'] ?? ''),
                ($payload['data']['payment']['payment_status'] ?? '') === 'SUCCESS' ? 'success' : 'failed',
                (float) ($payload['data']['payment']['payment_amount'] ?? 0),
                strtoupper((string) ($payload['data']['payment']['payment_currency'] ?? 'INR')),
                (array) ($payload['data']['order']['order_tags'] ?? []),
            ],
            default => ['', 'pending', 0.0, 'INR', []],
        };

        if ($txnId === '' || $status !== 'success') {
            return;
        }

        // Idempotency: unique (gateway, gateway_txn_id)
        $existing = DB::table('transactions')->where('gateway', $gateway)->where('gateway_txn_id', $txnId)->first();
        if ($existing !== null && $existing['status'] === 'success') {
            return; // already processed — never double-credit
        }

        $tenantId = (int) ($notes['tenant_id'] ?? 0);
        $purpose = (string) ($notes['purpose'] ?? 'wallet_recharge');
        if ($tenantId <= 0 || !DB::table('tenants')->where('id', $tenantId)->exists()) {
            Logger::channel('webhook')->warning('Payment without valid tenant_id note', ['gateway' => $gateway, 'txn' => $txnId]);
            return;
        }

        DB::transaction(function () use ($existing, $gateway, $txnId, $tenantId, $purpose, $amount, $currency, $notes) {
            if ($existing !== null) {
                DB::table('transactions')->where('id', $existing['id'])->update(['status' => 'success', 'updated_at' => now()]);
            } else {
                DB::table('transactions')->insert([
                    'tenant_id' => $tenantId,
                    'gateway' => $gateway,
                    'gateway_txn_id' => $txnId,
                    'type' => $purpose === 'wallet_recharge' ? 'wallet_recharge' : 'payment',
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => 'success',
                    'meta' => json_encode($notes, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($purpose === 'wallet_recharge') {
                $wallet = DB::table('wallets')->where('tenant_id', $tenantId)->lockForUpdate()->first();
                $newBalance = (float) ($wallet['balance'] ?? 0) + $amount;
                if ($wallet !== null) {
                    DB::table('wallets')->where('id', $wallet['id'])->update(['balance' => $newBalance, 'updated_at' => now()]);
                } else {
                    DB::table('wallets')->insert(['tenant_id' => $tenantId, 'balance' => $newBalance, 'currency' => $currency, 'updated_at' => now()]);
                }
                DB::table('wallet_transactions')->insert([
                    'tenant_id' => $tenantId,
                    'type' => 'credit',
                    'amount' => $amount,
                    'balance_after' => $newBalance,
                    'reason' => 'Recharge via ' . $gateway,
                    'reference_type' => 'transaction',
                    'created_at' => now(),
                ]);
            }

            if ($purpose === 'subscription' && !empty($notes['plan_id'])) {
                $plan = DB::table('plans')->where('id', (int) $notes['plan_id'])->first();
                if ($plan !== null) {
                    $cycle = ($notes['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
                    $periodEnd = date('Y-m-d H:i:s', strtotime($cycle === 'yearly' ? '+1 year' : '+1 month'));
                    DB::table('tenants')->where('id', $tenantId)->update([
                        'plan_id' => (int) $plan['id'],
                        'subscription_ends_at' => $periodEnd,
                        'updated_at' => now(),
                    ]);
                    DB::table('subscriptions')->insert([
                        'tenant_id' => $tenantId,
                        'plan_id' => (int) $plan['id'],
                        'billing_cycle' => $cycle,
                        'status' => 'active',
                        'amount' => $amount,
                        'currency' => $currency,
                        'gateway' => $gateway,
                        'gateway_subscription_id' => $txnId,
                        'current_period_start' => now(),
                        'current_period_end' => $periodEnd,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        DB::table('notifications')->insert([
            'tenant_id' => $tenantId,
            'type' => 'payment.received',
            'title' => __('billing.payment_received', 'Payment received'),
            'body' => \App\Core\Money::format($amount, $currency) . ' via ' . $gateway,
            'link' => '/tenant/billing',
            'created_at' => now(),
        ]);
    }

    private function secret(string $key): string
    {
        $encrypted = (string) setting($key, '');
        if ($encrypted === '') {
            return '';
        }
        return Crypt::decrypt($encrypted) ?? '';
    }

    private function verifyRazorpay(Request $request, string $raw): bool
    {
        $secret = $this->secret('gateway_razorpay_webhook_secret');
        $signature = (string) ($request->header('X-Razorpay-Signature') ?? '');
        if ($secret === '' || $signature === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $raw, $secret), $signature);
    }

    private function verifyStripe(Request $request, string $raw): bool
    {
        $secret = $this->secret('gateway_stripe_webhook_secret');
        $header = (string) ($request->header('Stripe-Signature') ?? '');
        if ($secret === '' || $header === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            $kv = explode('=', trim($pair), 2);
            if (count($kv) === 2) {
                $parts[$kv[0]][] = $kv[1];
            }
        }
        $timestamp = (string) ($parts['t'][0] ?? '');
        if ($timestamp === '' || abs(time() - (int) $timestamp) > 300) {
            return false; // replay guard
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
        foreach ((array) ($parts['v1'] ?? []) as $candidate) {
            if (hash_equals($expected, (string) $candidate)) {
                return true;
            }
        }
        return false;
    }

    private function verifyCashfree(Request $request, string $raw): bool
    {
        $secret = $this->secret('gateway_cashfree_webhook_secret');
        $signature = (string) ($request->header('x-webhook-signature') ?? '');
        $timestamp = (string) ($request->header('x-webhook-timestamp') ?? '');
        if ($secret === '' || $signature === '' || $timestamp === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $timestamp . $raw, $secret, true));
        return hash_equals($expected, $signature);
    }
}
