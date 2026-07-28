<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\DB;
use App\Services\Meta\MessageSender;
use App\Services\Meta\TemplateService;

/**
 * Sends one campaign message to one recipient (queued by CampaignBuilder's
 * throttled dispatcher).
 */
final class SendCampaignJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $campaignId = (int) ($payload['campaign_id'] ?? 0);
        $recipientId = (int) ($payload['recipient_id'] ?? 0);

        $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $recipient = DB::table('campaign_recipients')->where('id', $recipientId)->first();
        if ($campaign === null || $recipient === null || $recipient['status'] !== 'queued') {
            return;
        }
        // Respect pause/cancel that happened after queueing
        if (!in_array($campaign['status'], ['running'], true)) {
            DB::table('campaign_recipients')->where('id', $recipientId)->update(['status' => 'pending']);
            return;
        }

        $contact = DB::table('contacts')->where('id', $recipient['contact_id'])->first();
        if ($contact === null || (int) $contact['opt_in'] !== 1 || (int) $contact['is_blocked'] === 1) {
            DB::table('campaign_recipients')->where('id', $recipientId)->update(['status' => 'skipped']);
            return;
        }

        try {
            $content = json_decode((string) ($campaign['content'] ?? '{}'), true) ?: [];

            if ($campaign['message_type'] === 'template') {
                $template = DB::table('templates')->where('id', $campaign['template_id'])->first();
                if ($template === null || $template['status'] !== 'APPROVED') {
                    throw new \RuntimeException('Template unavailable or not approved.');
                }
                $mapping = json_decode((string) ($campaign['variable_mapping'] ?? '{}'), true) ?: [];
                $components = TemplateService::buildSendComponents(
                    $template,
                    $mapping,
                    $contact,
                    \App\Models\Contact::fieldValues((int) $contact['id'])
                );
                $message = MessageSender::sendToContact($contact, 'template', [
                    'name' => $template['name'],
                    'language' => $template['language'],
                    'components' => $components,
                ], [
                    'campaign_id' => $campaignId,
                    'template_category' => strtolower((string) $template['category']),
                ]);
            } else {
                // Free-form (text/media) — only lands inside open 24h windows
                $type = (string) ($content['type'] ?? 'text');
                $message = MessageSender::sendToContact($contact, $type, $content, ['campaign_id' => $campaignId]);
            }

            DB::table('campaign_recipients')->where('id', $recipientId)->update([
                'status' => 'sent',
                'message_id' => (int) ($message['id'] ?? 0) ?: null,
                'cost' => $message['cost'] ?? null,
                'sent_at' => now(),
            ]);
            DB::table('campaigns')->where('id', $campaignId)->increment('sent_count', 1, [
                'total_cost' => \App\Core\DB::raw('`total_cost` + ' . sprintf('%F', (float) ($message['cost'] ?? 0))),
            ]);

            // Frequency cap bookkeeping
            self::bumpFrequency((int) $campaign['tenant_id'], (int) $contact['id']);
        } catch (\Throwable $e) {
            DB::table('campaign_recipients')->where('id', $recipientId)->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 255),
            ]);
            DB::table('campaigns')->where('id', $campaignId)->increment('failed_count');
            // Swallow: one failed recipient must not retry the whole job
        }
    }

    private static function bumpFrequency(int $tenantId, int $contactId): void
    {
        $capDays = 7;
        $row = DB::table('frequency_caps')->where('contact_id', $contactId)->where('category', 'marketing')->first();
        if ($row === null) {
            try {
                DB::table('frequency_caps')->insert([
                    'tenant_id' => $tenantId,
                    'contact_id' => $contactId,
                    'category' => 'marketing',
                    'sent_count' => 1,
                    'window_started_at' => now(),
                ]);
            } catch (\Throwable) {
                // race — fall through to update
                DB::table('frequency_caps')->where('contact_id', $contactId)->where('category', 'marketing')->increment('sent_count');
            }
            return;
        }
        if (strtotime((string) $row['window_started_at']) < time() - $capDays * 86400) {
            DB::table('frequency_caps')->where('id', $row['id'])->update([
                'sent_count' => 1,
                'window_started_at' => now(),
            ]);
        } else {
            DB::table('frequency_caps')->where('id', $row['id'])->increment('sent_count');
        }
    }
}
