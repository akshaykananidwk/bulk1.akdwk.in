<?php

declare(strict_types=1);

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Core\Crypt;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\Contact;

/**
 * E-commerce store webhooks (Shopify HMAC / WooCommerce signature /
 * custom shared-secret). Syncs orders + triggers abandoned-cart flows.
 */
final class EcomWebhookController extends Controller
{
    public function receive(Request $request): never
    {
        $storeId = (int) $request->route('store');
        $store = DB::table('stores')->where('id', $storeId)->where('is_active', 1)->first();
        if ($store === null) {
            Response::json(['success' => false], 404);
        }

        $raw = $request->rawBody();
        if (!$this->verify($store, $request, $raw)) {
            Logger::channel('webhook')->warning('Ecom webhook signature failed', ['store' => $storeId]);
            Response::json(['success' => false], 403);
        }

        $payload = json_decode($raw, true) ?: [];
        $topic = (string) ($request->header('X-Shopify-Topic')
            ?? $request->header('X-WC-Webhook-Topic')
            ?? $request->str('topic', 'order.updated'));

        DB::table('webhook_logs')->insert([
            'tenant_id' => (int) $store['tenant_id'],
            'direction' => 'in',
            'source' => 'ecom_' . $store['platform'],
            'event' => mb_substr($topic, 0, 100),
            'payload' => mb_substr($raw, 0, 65000),
            'status' => 'received',
            'created_at' => now(),
        ]);

        \App\Core\Tenant::setId((int) $store['tenant_id']);

        try {
            if (str_contains($topic, 'order')) {
                $this->upsertOrder($store, $payload);
            } elseif (str_contains($topic, 'checkout') || str_contains($topic, 'cart')) {
                $this->upsertCart($store, $payload);
            }
        } catch (\Throwable $e) {
            Logger::channel('webhook')->error('Ecom webhook processing failed', ['error' => $e->getMessage()]);
        }

        Response::json(['success' => true]);
    }

    private function verify(array $store, Request $request, string $raw): bool
    {
        $secret = $store['webhook_secret'] !== null && $store['webhook_secret'] !== ''
            ? (Crypt::decrypt((string) $store['webhook_secret']) ?? (string) $store['webhook_secret'])
            : '';
        if ($secret === '') {
            return false; // secrets are mandatory
        }

        // Shopify: base64 HMAC-SHA256 header
        $shopify = (string) ($request->header('X-Shopify-Hmac-Sha256') ?? '');
        if ($shopify !== '') {
            return hash_equals(base64_encode(hash_hmac('sha256', $raw, $secret, true)), $shopify);
        }
        // WooCommerce: base64 HMAC-SHA256 header
        $woo = (string) ($request->header('X-WC-Webhook-Signature') ?? '');
        if ($woo !== '') {
            return hash_equals(base64_encode(hash_hmac('sha256', $raw, $secret, true)), $woo);
        }
        // Custom: hex HMAC in X-Signature
        $custom = (string) ($request->header('X-Signature') ?? '');
        if ($custom !== '') {
            return hash_equals(hash_hmac('sha256', $raw, $secret), $custom);
        }
        return false;
    }

    private function upsertOrder(array $store, array $payload): void
    {
        $externalId = (string) ($payload['id'] ?? $payload['order_id'] ?? '');
        if ($externalId === '') {
            return;
        }

        $phone = Sanitizer::phone((string) (
            $payload['phone']
            ?? $payload['customer']['phone']
            ?? $payload['billing']['phone']
            ?? $payload['billing_address']['phone']
            ?? ''
        ));
        $customerName = trim((string) (
            ($payload['customer']['first_name'] ?? $payload['billing']['first_name'] ?? '') . ' '
            . ($payload['customer']['last_name'] ?? $payload['billing']['last_name'] ?? '')
        )) ?: null;

        $contactId = null;
        $contact = null;
        if ($phone !== '') {
            $contact = Contact::firstOrCreateByPhone($phone, $customerName, 'ecommerce');
            $contactId = (int) $contact['id'];
        }

        $status = strtolower((string) ($payload['financial_status'] ?? $payload['status'] ?? 'pending'));
        $total = (float) ($payload['total_price'] ?? $payload['total'] ?? 0);
        $paymentMethod = strtolower((string) ($payload['payment_method'] ?? ($payload['gateway'] ?? '')));
        $isCod = str_contains($paymentMethod, 'cod') || str_contains($paymentMethod, 'cash');

        $row = [
            'contact_id' => $contactId,
            'order_number' => (string) ($payload['order_number'] ?? $payload['number'] ?? $externalId),
            'status' => mb_substr($status, 0, 32),
            'payment_status' => mb_substr((string) ($payload['financial_status'] ?? $payload['status'] ?? 'pending'), 0, 32),
            'payment_method' => mb_substr($paymentMethod, 0, 32) ?: null,
            'is_cod' => $isCod ? 1 : 0,
            'total' => $total,
            'currency' => strtoupper((string) ($payload['currency'] ?? 'INR')),
            'customer_details' => json_encode([
                'name' => $customerName,
                'phone' => $phone,
                'email' => $payload['email'] ?? $payload['billing']['email'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'placed_at' => isset($payload['created_at']) ? date('Y-m-d H:i:s', strtotime((string) $payload['created_at']) ?: time()) : now(),
            'updated_at' => now(),
        ];

        $existing = DB::table('orders')->where('store_id', $store['id'])->where('external_id', $externalId)->first();
        if ($existing !== null) {
            DB::table('orders')->where('id', $existing['id'])->update($row);
            $orderId = (int) $existing['id'];
            $isNew = false;
        } else {
            $orderId = DB::table('orders')->insert($row + [
                'tenant_id' => (int) $store['tenant_id'],
                'store_id' => (int) $store['id'],
                'external_id' => $externalId,
                'created_at' => now(),
            ]);
            $isNew = true;

            foreach ((array) ($payload['line_items'] ?? $payload['items'] ?? []) as $item) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'name' => mb_substr((string) ($item['name'] ?? $item['title'] ?? 'Item'), 0, 255),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'price' => (float) ($item['price'] ?? 0),
                ]);
            }
        }

        // Recovered cart?
        if ($contactId !== null) {
            DB::table('carts')->where('tenant_id', $store['tenant_id'])
                ->where('contact_id', $contactId)->where('status', 'abandoned')
                ->update(['status' => 'converted', 'updated_at' => now()]);
        }

        // Order-event flows (order confirmation / COD confirm)
        if ($isNew && $contact !== null) {
            $flows = DB::table('flows')
                ->where('tenant_id', $store['tenant_id'])
                ->where('status', 'active')
                ->where('trigger_type', 'order_event')
                ->get();
            foreach ($flows as $flow) {
                try {
                    \App\Services\Automation\FlowEngine::start($flow, $contact, null, [
                        'order' => [
                            'id' => $orderId,
                            'order_number' => $row['order_number'],
                            'total' => $total,
                            'status' => $status,
                            'is_cod' => $isCod,
                        ],
                        'trigger' => ['type' => 'order_event'],
                    ]);
                } catch (\Throwable $e) {
                    Logger::channel('app')->error('Order flow failed', ['error' => $e->getMessage()]);
                }
            }
        }
    }

    private function upsertCart(array $store, array $payload): void
    {
        $externalId = (string) ($payload['id'] ?? $payload['token'] ?? '');
        if ($externalId === '') {
            return;
        }
        $phone = Sanitizer::phone((string) ($payload['phone'] ?? $payload['customer']['phone'] ?? $payload['billing_address']['phone'] ?? ''));
        $contactId = null;
        if ($phone !== '') {
            $contact = Contact::firstOrCreateByPhone($phone, null, 'ecommerce');
            $contactId = (int) $contact['id'];
        }

        $row = [
            'contact_id' => $contactId,
            'items' => json_encode($payload['line_items'] ?? $payload['items'] ?? [], JSON_UNESCAPED_UNICODE),
            'total' => (float) ($payload['total_price'] ?? $payload['total'] ?? 0),
            'currency' => strtoupper((string) ($payload['currency'] ?? 'INR')),
            'checkout_url' => mb_substr((string) ($payload['abandoned_checkout_url'] ?? $payload['checkout_url'] ?? ''), 0, 500) ?: null,
            'status' => 'active',
            'updated_at' => now(),
        ];

        $existing = DB::table('carts')->where('store_id', $store['id'])->where('external_id', $externalId)->first();
        if ($existing !== null) {
            DB::table('carts')->where('id', $existing['id'])->update($row);
        } else {
            DB::table('carts')->insert($row + [
                'tenant_id' => (int) $store['tenant_id'],
                'store_id' => (int) $store['id'],
                'external_id' => $externalId,
                'created_at' => now(),
            ]);
        }
    }
}
