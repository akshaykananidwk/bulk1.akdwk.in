<?php
/**
 * Long-running queue worker (PM2 / supervisor):
 *   pm2 start cron/worker.php --name kwc-worker --interpreter php -- --queue=default,messages,webhook,ai
 *
 * Flags: --queue=a,b --sleep=1 --max-jobs=1000 --max-time=3600 --memory=256
 * Graceful shutdown on SIGTERM/SIGINT; pauses while maintenance.flag exists.
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

$options = getopt('', ['queue::', 'sleep::', 'max-jobs::', 'max-time::', 'memory::']);
$queues = array_filter(array_map('trim', explode(',', (string) ($options['queue'] ?? 'default,messages,webhook,ai,media,mail,reports'))));
$sleep = max(1, (int) ($options['sleep'] ?? Config::get('queue.worker.sleep', 1)));
$maxJobs = max(1, (int) ($options['max-jobs'] ?? Config::get('queue.worker.max_jobs', 1000)));
$maxTime = max(60, (int) ($options['max-time'] ?? Config::get('queue.worker.max_time', 3600)));
$memoryLimitMb = max(64, (int) ($options['memory'] ?? Config::get('queue.worker.memory_mb', 256)));

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

$workerId = 'worker-' . substr(sha1(gethostname() . getmypid() . microtime()), 0, 12);
$startedAt = time();
$processed = 0;

Logger::channel('queue')->info('Worker started', ['id' => $workerId, 'queues' => $queues]);

$heartbeat = STORAGE_PATH . '/locks/worker_heartbeat';

while (!$shouldStop) {
    @touch($heartbeat);

    // Pause (but stay alive) during updates
    if (is_file(STORAGE_PATH . '/maintenance.flag')) {
        sleep(2);
        continue;
    }

    try {
        $worked = Queue::work($queues, $workerId);
    } catch (\Throwable $e) {
        Logger::channel('queue')->error('Worker loop error', ['error' => $e->getMessage()]);
        $worked = false;
        sleep(2);
    }

    if ($worked) {
        $processed++;
    } else {
        sleep($sleep);
    }

    if ($processed >= $maxJobs) {
        Logger::channel('queue')->info('Worker restarting: max jobs reached', ['processed' => $processed]);
        break;
    }
    if ((time() - $startedAt) >= $maxTime) {
        Logger::channel('queue')->info('Worker restarting: max time reached');
        break;
    }
    if ((memory_get_usage(true) / 1048576) >= $memoryLimitMb) {
        Logger::channel('queue')->info('Worker restarting: memory limit reached');
        break;
    }
}

Logger::channel('queue')->info('Worker stopped', ['id' => $workerId, 'processed' => $processed]);
exit(0);
