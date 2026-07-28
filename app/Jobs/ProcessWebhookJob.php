<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\DB;
use App\Services\Meta\WebhookProcessor;

/**
 * Processes a stored Meta webhook payload from webhook_logs.
 */
final class ProcessWebhookJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $logId = (int) ($payload['webhook_log_id'] ?? 0);
        if ($logId <= 0) {
            return;
        }

        $log = DB::table('webhook_logs')->where('id', $logId)->first();
        if ($log === null || $log['status'] === 'processed') {
            return;
        }

        DB::table('webhook_logs')->where('id', $logId)->update(['status' => 'processing']);

        try {
            $data = json_decode((string) $log['payload'], true);
            if (!is_array($data)) {
                throw new \RuntimeException('Webhook payload is not valid JSON');
            }
            WebhookProcessor::process($data);
            DB::table('webhook_logs')->where('id', $logId)->update(['status' => 'processed']);
        } catch (\Throwable $e) {
            DB::table('webhook_logs')->where('id', $logId)->update([
                'status' => 'failed',
                'error' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            throw $e; // let the queue retry with backoff
        }
    }
}
