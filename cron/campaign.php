<?php
/**
 * Dedicated high-throughput campaign worker:
 *   pm2 start cron/campaign.php --name kwc-campaign --interpreter php
 *
 * Processes only the `campaign` queue so bulk broadcasts never starve
 * transactional messages.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Logger;
use App\Core\Queue;

if (!Config::isInstalled()) {
    fwrite(STDERR, "Not installed yet.\n");
    exit(1);
}

$shouldStop = false;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$shouldStop) {
        $shouldStop = true;
    });
    pcntl_signal(SIGINT, function () use (&$shouldStop) {
        $shouldStop = true;
    });
}

$workerId = 'campaign-' . substr(sha1(gethostname() . getmypid() . microtime()), 0, 12);
$startedAt = time();
$processed = 0;

Logger::channel('queue')->info('Campaign worker started', ['id' => $workerId]);

$heartbeat = STORAGE_PATH . '/locks/campaign_heartbeat';

while (!$shouldStop) {
    @touch($heartbeat);

    if (is_file(STORAGE_PATH . '/maintenance.flag')) {
        sleep(2);
        continue;
    }

    try {
        $worked = Queue::work(['campaign'], $workerId);
    } catch (\Throwable $e) {
        Logger::channel('queue')->error('Campaign worker error', ['error' => $e->getMessage()]);
        $worked = false;
        sleep(2);
    }

    if ($worked) {
        $processed++;
    } else {
        sleep(1);
    }

    // Recycle hourly to avoid slow leaks
    if ((time() - $startedAt) >= 3600 || $processed >= 5000) {
        break;
    }
}

Logger::channel('queue')->info('Campaign worker stopped', ['processed' => $processed]);
exit(0);
