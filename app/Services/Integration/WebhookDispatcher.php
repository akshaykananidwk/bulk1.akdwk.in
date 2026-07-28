<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Core\DB;
use App\Core\Hash;
use App\Core\Queue;

/**
 * Outbound webhooks: event subscriptions, HMAC signature, retries with
 * exponential backoff (via the queue), delivery logs.
 */
final class WebhookDispatcher
{
    /**
     * Queue delivery of an event to all matching tenant webhooks
     * (and Zapier-style REST hooks).
     */
    public static function dispatch(int $tenantId, string $event, array $payload): void
    {
        $webhooks = DB::table('webhooks')
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->get();
        foreach ($webhooks as $webhook) {
            $events = json_decode((string) $webhook['events'], true) ?: [];
            if (!in_array('*', $events, true) && !in_array($event, $events, true)) {
                continue;
            }
            Queue::push(\App\Jobs\DeliverWebhookJob::class, [
                'webhook_id' => (int) $webhook['id'],
                'event' => $event,
                'payload' => $payload,
            ], 'default', 6, 0, $tenantId);
        }

        $zaps = DB::table('zapier_subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('event', $event)
            ->get();
        foreach ($zaps as $zap) {
            Queue::push(\App\Jobs\DeliverWebhookJob::class, [
                'zap_url' => (string) $zap['target_url'],
                'event' => $event,
                'payload' => $payload,
            ], 'default', 6, 0, $tenantId);
        }
    }

    /**
     * Deliver one webhook (called by the job). Throws to trigger queue retry.
     */
    public static function deliver(array $jobPayload): void
    {
        $event = (string) ($jobPayload['event'] ?? '');
        $payload = (array) ($jobPayload['payload'] ?? []);
        $body = json_encode([
            'event' => $event,
            'data' => $payload,
            'sent_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE) ?: '{}';

        if (!empty($jobPayload['zap_url'])) {
            $response = \App\Core\Http::make()->timeout(15)->post((string) $jobPayload['zap_url'], $body);
            if (!$response->ok() && $response->status !== 410) {
                throw new \RuntimeException('Zapier hook delivery failed: HTTP ' . $response->status);
            }
            // 410 Gone → Zapier unsubscribed
            if ($response->status === 410) {
                DB::table('zapier_subscriptions')->where('target_url', (string) $jobPayload['zap_url'])->delete();
            }
            return;
        }

        $webhook = DB::table('webhooks')->where('id', (int) ($jobPayload['webhook_id'] ?? 0))->first();
        if ($webhook === null || (int) $webhook['is_active'] !== 1) {
            return;
        }

        $signature = Hash::hmac($body, (string) $webhook['secret']);
        $response = \App\Core\Http::make()
            ->timeout(15)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-KWC-Event' => $event,
                'X-KWC-Signature' => 'sha256=' . $signature,
            ])
            ->post((string) $webhook['url'], $body);

        DB::table('webhook_logs')->insert([
            'tenant_id' => (int) $webhook['tenant_id'],
            'direction' => 'out',
            'source' => 'kwc',
            'webhook_id' => (int) $webhook['id'],
            'event' => $event,
            'payload' => $body,
            'response_code' => $response->status,
            'status' => $response->ok() ? 'delivered' : 'failed',
            'created_at' => now(),
        ]);

        if ($response->ok()) {
            DB::table('webhooks')->where('id', $webhook['id'])->update([
                'failure_count' => 0,
                'last_triggered_at' => now(),
            ]);
            return;
        }

        $failures = (int) $webhook['failure_count'] + 1;
        $update = ['failure_count' => $failures, 'last_triggered_at' => now()];
        if ($failures >= 20) {
            $update['is_active'] = 0; // circuit breaker after persistent failure
        }
        DB::table('webhooks')->where('id', $webhook['id'])->update($update);

        throw new \RuntimeException('Webhook delivery failed: HTTP ' . $response->status);
    }
}
