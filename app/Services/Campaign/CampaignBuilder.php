<?php

declare(strict_types=1);

namespace App\Services\Campaign;

use App\Core\DB;
use App\Core\Event;
use App\Core\Logger;
use App\Core\Queue;
use App\Core\Tenant;

/**
 * Campaign lifecycle: audience resolution, launch, throttled dispatch,
 * pause/resume/cancel, completion. Dispatch runs every minute from the
 * scheduler; each tick queues up to throttle_per_minute sends per campaign.
 */
final class CampaignBuilder
{
    /**
     * Resolve the audience into campaign_recipients (deduped, opt-in only,
     * exclusion lists + frequency caps honoured). Returns recipient count.
     */
    public static function buildAudience(array $campaign): int
    {
        $tenantId = (int) $campaign['tenant_id'];
        $config = json_decode((string) ($campaign['audience_config'] ?? '{}'), true) ?: [];

        $query = DB::table('contacts')
            ->where('tenant_id', $tenantId)
            ->where('opt_in', 1)
            ->where('is_blocked', 0);

        switch ($campaign['audience_type']) {
            case 'groups':
                $groupIds = array_map('intval', (array) ($config['group_ids'] ?? []));
                $contactIds = $groupIds
                    ? array_map('intval', DB::table('group_contacts')->whereIn('group_id', $groupIds)->pluck('contact_id'))
                    : [];
                $query->whereIn('id', $contactIds);
                break;
            case 'tags':
                $tagIds = array_map('intval', (array) ($config['tag_ids'] ?? []));
                $contactIds = $tagIds
                    ? array_map('intval', DB::table('contact_tags')->whereIn('tag_id', $tagIds)->pluck('contact_id'))
                    : [];
                $query->whereIn('id', $contactIds);
                break;
            case 'segments':
                $segmentIds = array_map('intval', (array) ($config['segment_ids'] ?? []));
                $contactIds = [];
                foreach ($segmentIds as $segmentId) {
                    $segment = DB::table('segments')->where('id', $segmentId)->where('tenant_id', $tenantId)->first();
                    if ($segment !== null) {
                        $contactIds = array_merge($contactIds, SegmentResolver::contactIds($segment));
                    }
                }
                $query->whereIn('id', array_values(array_unique($contactIds)));
                break;
            case 'manual':
                $query->whereIn('id', array_map('intval', (array) ($config['contact_ids'] ?? [])));
                break;
            case 'all':
            default:
                break;
        }

        // Exclusion lists (newline/comma-separated phone lists)
        $excludedPhones = [];
        foreach (array_map('intval', (array) json_decode((string) ($campaign['exclusion_list_ids'] ?? '[]'), true)) as $listId) {
            $list = DB::table('exclusion_lists')->where('id', $listId)->where('tenant_id', $tenantId)->first();
            if ($list !== null) {
                foreach (preg_split('/[\s,;]+/', (string) $list['phone_numbers']) ?: [] as $phone) {
                    $phone = \App\Core\Sanitizer::phone($phone);
                    if ($phone !== '') {
                        $excludedPhones[$phone] = true;
                    }
                }
            }
        }

        $count = 0;
        $offset = 0;
        while (true) {
            $batch = (clone $query)->select('id', 'phone')->orderBy('id')->limit(1000)->offset($offset)->get();
            if (empty($batch)) {
                break;
            }
            $rows = [];
            foreach ($batch as $contact) {
                if (isset($excludedPhones[$contact['phone']])) {
                    continue;
                }
                // Frequency cap: max N marketing msgs per contact per window
                if ((int) $campaign['respect_frequency_cap'] === 1 && self::frequencyCapped($tenantId, (int) $contact['id'])) {
                    continue;
                }
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'campaign_id' => (int) $campaign['id'],
                    'contact_id' => (int) $contact['id'],
                    'status' => 'pending',
                    'created_at' => now(),
                ];
                $count++;
            }
            if ($rows) {
                // INSERT IGNORE semantics via unique key — duplicates skipped
                foreach (array_chunk($rows, 500) as $chunk) {
                    try {
                        DB::table('campaign_recipients')->insertMany($chunk);
                    } catch (\Throwable) {
                        foreach ($chunk as $row) {
                            try {
                                DB::table('campaign_recipients')->insert($row);
                            } catch (\Throwable) {
                                $count--; // duplicate
                            }
                        }
                    }
                }
            }
            $offset += 1000;
        }

        DB::table('campaigns')->where('id', $campaign['id'])->update([
            'total_recipients' => DB::table('campaign_recipients')->where('campaign_id', $campaign['id'])->count(),
            'updated_at' => now(),
        ]);

        return $count;
    }

    private static function frequencyCapped(int $tenantId, int $contactId): bool
    {
        $capCount = (int) (Tenant::setting('frequency_cap_count') ?? 0);
        $capDays = (int) (Tenant::setting('frequency_cap_days') ?? 7);
        if ($capCount <= 0) {
            return false;
        }
        $row = DB::table('frequency_caps')->where('contact_id', $contactId)->where('category', 'marketing')->first();
        if ($row === null) {
            return false;
        }
        $windowStart = strtotime((string) $row['window_started_at']);
        if ($windowStart < time() - $capDays * 86400) {
            return false; // window expired
        }
        return (int) $row['sent_count'] >= $capCount;
    }

    public static function launch(array $campaign): void
    {
        if (!in_array($campaign['status'], ['draft', 'scheduled'], true)) {
            throw new \RuntimeException(__('campaigns.not_launchable', 'This campaign cannot be launched from its current state.'));
        }
        if ((int) $campaign['total_recipients'] === 0) {
            self::buildAudience($campaign);
            $campaign = DB::table('campaigns')->where('id', $campaign['id'])->first() ?? $campaign;
            if ((int) $campaign['total_recipients'] === 0) {
                throw new \RuntimeException(__('campaigns.no_recipients', 'No eligible recipients (check opt-ins and filters).'));
            }
        }

        $update = ['updated_at' => now()];
        if (!empty($campaign['scheduled_at']) && strtotime((string) $campaign['scheduled_at']) > time()) {
            $update['status'] = 'scheduled';
        } else {
            $update['status'] = 'running';
            $update['started_at'] = now();
        }
        DB::table('campaigns')->where('id', $campaign['id'])->update($update);
        audit_log('campaigns.launched', 'campaign', (int) $campaign['id']);
    }

    /**
     * Scheduler tick (every minute): start due scheduled campaigns and
     * queue the next throttled batch for each running campaign.
     */
    public static function dispatchDue(): void
    {
        // Promote due scheduled campaigns
        DB::table('campaigns')
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->update(['status' => 'running', 'started_at' => now(), 'updated_at' => now()]);

        $running = DB::table('campaigns')->where('status', 'running')->get();
        foreach ($running as $campaign) {
            self::dispatchBatch($campaign);
        }
    }

    private static function dispatchBatch(array $campaign): void
    {
        $throttle = max(1, (int) $campaign['throttle_per_minute']);

        $pending = DB::table('campaign_recipients')
            ->where('campaign_id', $campaign['id'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($throttle)
            ->get();

        if (empty($pending)) {
            // No queued work left either? → complete
            $stillQueued = DB::table('campaign_recipients')
                ->where('campaign_id', $campaign['id'])
                ->where('status', 'queued')
                ->exists();
            if (!$stillQueued) {
                DB::table('campaigns')->where('id', $campaign['id'])->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
                Event::publish('notifications', 'notification', [
                    'title' => __('campaigns.completed', 'Campaign completed: :name', ['name' => (string) $campaign['name']]),
                    'link' => '/tenant/campaigns/' . $campaign['id'],
                ], (int) $campaign['tenant_id']);
                \App\Core\Event::fire('campaign.completed', ['campaign_id' => (int) $campaign['id']]);
            }
            return;
        }

        foreach ($pending as $recipient) {
            DB::table('campaign_recipients')->where('id', $recipient['id'])->update(['status' => 'queued']);
            Queue::push(\App\Jobs\SendCampaignJob::class, [
                'campaign_id' => (int) $campaign['id'],
                'recipient_id' => (int) $recipient['id'],
            ], 'campaign', 5, 0, (int) $campaign['tenant_id']);
        }
    }

    public static function control(array $campaign, string $action): void
    {
        $map = [
            'pause' => ['running' => 'paused', 'scheduled' => 'paused'],
            'resume' => ['paused' => 'running'],
            'cancel' => ['running' => 'cancelled', 'paused' => 'cancelled', 'scheduled' => 'cancelled', 'draft' => 'cancelled'],
        ];
        $transitions = $map[$action] ?? [];
        $current = (string) $campaign['status'];
        if (!isset($transitions[$current])) {
            throw new \RuntimeException(__('campaigns.bad_transition', 'Cannot :a a :s campaign.', ['a' => $action, 's' => $current]));
        }
        DB::table('campaigns')->where('id', $campaign['id'])->update([
            'status' => $transitions[$current],
            'updated_at' => now(),
        ]);
        audit_log('campaigns.' . $action, 'campaign', (int) $campaign['id']);
    }
}
