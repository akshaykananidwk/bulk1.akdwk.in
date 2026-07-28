<?php
/**
 * Watchdog — run every 5 minutes via cron (crontab entry):
 *   "*_/5 * * * *" (replace _ with nothing) php /path/to/cron/watchdog.php
 *
 * Releases stuck jobs, alerts on queue backlog, verifies worker heartbeats.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Mail;
use App\Core\Queue;

if (!Config::isInstalled() || is_file(STORAGE_PATH . '/maintenance.flag')) {
    exit(0);
}

// 1. Release jobs whose worker died mid-run
$released = Queue::releaseStuck();
if ($released > 0) {
    Logger::channel('queue')->warning('Watchdog released stuck jobs', ['count' => $released]);
}

// 2. Queue backlog alert (threshold configurable via settings)
$threshold = (int) setting('queue_backlog_alert', '5000');
$backlog = Queue::size();
if ($threshold > 0 && $backlog > $threshold) {
    $lastAlert = (int) setting('queue_backlog_last_alert', '0');
    if (time() - $lastAlert > 3600) { // alert at most hourly
        set_setting('queue_backlog_last_alert', (string) time());
        Logger::channel('queue')->error('Queue backlog high', ['size' => $backlog]);
        $adminEmail = (string) setting('alert_email', '');
        if ($adminEmail !== '') {
            Mail::make()
                ->to($adminEmail)
                ->subject('[Krishna WhatsApp Cloud] Queue backlog alert: ' . $backlog . ' jobs')
                ->text("The job queue has {$backlog} pending jobs (threshold {$threshold}).\n\nCheck that your workers are running: pm2 status\nOr see Admin -> System -> Queue Monitor.")
                ->send();
        }
    }
}

// 3. Worker heartbeat check — if PM2 workers are expected but silent, log it
$mode = (string) setting('worker_mode', 'cron'); // cron | pm2
if ($mode === 'pm2') {
    $heartbeat = STORAGE_PATH . '/locks/worker_heartbeat';
    $stale = !is_file($heartbeat) || (time() - (int) @filemtime($heartbeat)) > 300;
    if ($stale) {
        Logger::channel('queue')->error('Worker heartbeat missing — PM2 worker appears down. Cron fallback will process jobs.');
    }
}

// 4. Failed jobs spike alert
$recentFailed = DB::table('failed_jobs')
    ->where('failed_at', '>', date('Y-m-d H:i:s', time() - 3600))
    ->count();
if ($recentFailed > 50) {
    Logger::channel('queue')->error('High failure rate', ['failed_last_hour' => $recentFailed]);
}

exit(0);
