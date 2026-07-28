<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\DB;
use App\Core\Queue;
use App\Services\Meta\TemplateService;

/**
 * Syncs message templates from Meta for one WABA account.
 * enqueueAll() fans out one job per active WABA (scheduler, every 30 min).
 */
final class SyncTemplatesJob implements JobInterface
{
    public static function enqueueAll(): void
    {
        $wabas = DB::table('waba_accounts')->where('status', 'active')->get();
        foreach ($wabas as $waba) {
            Queue::push(self::class, ['waba_account_id' => (int) $waba['id']], 'default', 6, 0, (int) $waba['tenant_id']);
        }
    }

    public function handle(array $payload): void
    {
        $wabaId = (int) ($payload['waba_account_id'] ?? 0);
        if ($wabaId <= 0) {
            return;
        }
        $waba = DB::table('waba_accounts')->where('id', $wabaId)->first();
        if ($waba === null || $waba['status'] !== 'active') {
            return;
        }
        TemplateService::syncFromMeta($waba);
    }
}
