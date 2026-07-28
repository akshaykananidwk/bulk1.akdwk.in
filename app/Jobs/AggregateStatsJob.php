<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\DB;

/**
 * Hourly stats aggregation into stats_daily (per tenant, per metric).
 */
final class AggregateStatsJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $date = (string) ($payload['date'] ?? date('Y-m-d'));
        $start = $date . ' 00:00:00';
        $end = $date . ' 23:59:59';
        $prefix = DB::prefix();

        $metrics = [
            'messages_in' => 'SELECT tenant_id, COUNT(*) AS v FROM `' . $prefix . "messages` WHERE direction = 'in' AND created_at BETWEEN ? AND ? GROUP BY tenant_id",
            'messages_out' => 'SELECT tenant_id, COUNT(*) AS v FROM `' . $prefix . "messages` WHERE direction = 'out' AND created_at BETWEEN ? AND ? GROUP BY tenant_id",
            'messages_failed' => 'SELECT tenant_id, COUNT(*) AS v FROM `' . $prefix . "messages` WHERE status = 'failed' AND created_at BETWEEN ? AND ? GROUP BY tenant_id",
            'message_cost' => 'SELECT tenant_id, COALESCE(SUM(cost),0) AS v FROM `' . $prefix . 'messages` WHERE created_at BETWEEN ? AND ? GROUP BY tenant_id',
            'new_contacts' => 'SELECT tenant_id, COUNT(*) AS v FROM `' . $prefix . 'contacts` WHERE created_at BETWEEN ? AND ? GROUP BY tenant_id',
            'conversations_opened' => 'SELECT tenant_id, COUNT(*) AS v FROM `' . $prefix . 'conversations` WHERE created_at BETWEEN ? AND ? GROUP BY tenant_id',
        ];

        foreach ($metrics as $metric => $sql) {
            $rows = DB::select($sql, [$start, $end]);
            foreach ($rows as $row) {
                $tenantId = (int) $row['tenant_id'];
                $value = (float) $row['v'];
                $updated = DB::table('stats_daily')
                    ->where('tenant_id', $tenantId)
                    ->where('date', $date)
                    ->where('metric', $metric)
                    ->update(['value' => $value]);
                if ($updated === 0) {
                    try {
                        DB::table('stats_daily')->insert([
                            'tenant_id' => $tenantId,
                            'date' => $date,
                            'metric' => $metric,
                            'value' => $value,
                        ]);
                    } catch (\Throwable) {
                        // concurrent insert — retry as update
                        DB::table('stats_daily')
                            ->where('tenant_id', $tenantId)
                            ->where('date', $date)
                            ->where('metric', $metric)
                            ->update(['value' => $value]);
                    }
                }
            }
        }
    }
}
