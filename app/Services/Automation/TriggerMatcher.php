<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Core\DB;
use App\Core\Logger;

/**
 * Matches inbound messages against keywords and flow triggers, and feeds
 * waiting flow runs. Called by the webhook processor.
 */
final class TriggerMatcher
{
    public static function onInboundMessage(int $tenantId, int $conversationId, array $contact, string $type, string $body, array $extra): void
    {
        // 1. A run waiting for this contact's reply consumes the message first
        if (FlowEngine::onReply($tenantId, (int) $contact['id'], $body, $extra)) {
            return;
        }

        $normalized = mb_strtolower(trim($body));
        if ($normalized === '') {
            return;
        }

        // 2. Keyword rules (exact / contains / starts_with / regex)
        $keywords = DB::table('keywords')->where('tenant_id', $tenantId)->where('is_active', 1)->get();
        foreach ($keywords as $keyword) {
            $pattern = mb_strtolower(trim((string) $keyword['keyword']));
            $matched = match ($keyword['match_type']) {
                'contains' => mb_stripos($normalized, $pattern) !== false,
                'starts_with' => str_starts_with($normalized, $pattern),
                'regex' => @preg_match('/' . str_replace('/', '\/', (string) $keyword['keyword']) . '/iu', $body) === 1,
                default => $normalized === $pattern,
            };
            if (!$matched) {
                continue;
            }

            DB::table('keywords')->where('id', $keyword['id'])->increment('hit_count');

            if (!empty($keyword['flow_id'])) {
                $flow = DB::table('flows')->where('id', $keyword['flow_id'])->where('status', 'active')->first();
                if ($flow !== null) {
                    try {
                        FlowEngine::start($flow, $contact, $conversationId, ['trigger' => ['type' => 'keyword', 'keyword' => $keyword['keyword'], 'message' => $body]]);
                    } catch (\Throwable $e) {
                        Logger::channel('app')->error('Keyword flow start failed', ['error' => $e->getMessage()]);
                    }
                    return; // first match wins
                }
            }
            if (!empty($keyword['reply_text'])) {
                try {
                    \App\Services\Meta\MessageSender::sendToContact($contact, 'text', [
                        'body' => \App\Core\Str::interpolate((string) $keyword['reply_text'], ['contact' => $contact]),
                    ]);
                } catch (\Throwable $e) {
                    Logger::channel('app')->error('Keyword auto-reply failed', ['error' => $e->getMessage()]);
                }
                return;
            }
        }

        // 3. "any message" triggers (first contact greeting etc.)
        $anyFlows = DB::table('flows')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('trigger_type', 'any_message')
            ->get();
        foreach ($anyFlows as $flow) {
            try {
                FlowEngine::start($flow, $contact, $conversationId, ['trigger' => ['type' => 'any_message', 'message' => $body]]);
            } catch (\Throwable $e) {
                Logger::channel('app')->error('Any-message flow start failed', ['error' => $e->getMessage()]);
            }
        }

        // 4. New-contact triggers
        if (($contact['created_at'] ?? '') >= date('Y-m-d H:i:s', time() - 120)) {
            $newContactFlows = DB::table('flows')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('trigger_type', 'new_contact')
                ->get();
            foreach ($newContactFlows as $flow) {
                try {
                    FlowEngine::start($flow, $contact, $conversationId, ['trigger' => ['type' => 'new_contact']]);
                } catch (\Throwable $e) {
                    Logger::channel('app')->error('New-contact flow start failed', ['error' => $e->getMessage()]);
                }
            }
        }
    }
}
