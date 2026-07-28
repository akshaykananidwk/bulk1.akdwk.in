<?php
/**
 * Cron entrypoint — run every minute:
 *   * * * * * /usr/bin/php /path/to/cron/scheduler.php >> /dev/null 2>&1
 *
 * Dispatches scheduled tasks and, in cron-only mode, drains the queue for
 * up to ~50 seconds so the platform works without PM2/supervisor.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Core\Event;
use App\Core\Logger;
use App\Core\Queue;
use App\Core\Scheduler;

if (!Config::isInstalled()) {
    exit(0);
}

// Updater maintenance flag pauses all scheduled work
if (is_file(STORAGE_PATH . '/maintenance.flag')) {
    exit(0);
}

$scheduler = new Scheduler();

// -- Every minute -----------------------------------------------------------
$scheduler->task('queue.release_stuck', 'every_5_minutes', function () {
    Queue::releaseStuck();
});

$scheduler->task('campaigns.dispatch', 'every_minute', function () {
    if (class_exists(\App\Services\Campaign\CampaignBuilder::class)) {
        \App\Services\Campaign\CampaignBuilder::dispatchDue();
    }
});

$scheduler->task('scheduled_actions.run', 'every_minute', function () {
    if (class_exists(\App\Services\Automation\FlowEngine::class)) {
        \App\Services\Automation\FlowEngine::resumeDueRuns();
    }
});

// -- Periodic ---------------------------------------------------------------
$scheduler->task('events.prune', 'every_30_minutes', function () {
    Event::prune();
});

$scheduler->task('templates.sync', 'every_30_minutes', function () {
    if (class_exists(\App\Jobs\SyncTemplatesJob::class)) {
        \App\Jobs\SyncTemplatesJob::enqueueAll();
    }
});

$scheduler->task('sessions.prune', 'hourly', function () {
    DB::table('user_sessions')->where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
});

$scheduler->task('logs.prune', 'daily', function () {
    DB::table('webhook_logs')->where('created_at', '<', date('Y-m-d H:i:s', time() - 14 * 86400))->delete();
    DB::table('api_logs')->where('created_at', '<', date('Y-m-d H:i:s', time() - 30 * 86400))->delete();
    DB::table('cron_logs')->where('ran_at', '<', date('Y-m-d H:i:s', time() - 7 * 86400))->delete();
    DB::table('login_attempts')->where('created_at', '<', date('Y-m-d H:i:s', time() - 30 * 86400))->delete();
    // Rotate file logs older than 14 days
    foreach (glob(STORAGE_PATH . '/logs/*.log') ?: [] as $file) {
        if (filemtime($file) < time() - 14 * 86400) {
            @unlink($file);
        }
    }
});

$scheduler->task('stats.aggregate', 'hourly', function () {
    if (class_exists(\App\Jobs\AggregateStatsJob::class)) {
        (new \App\Jobs\AggregateStatsJob())->handle([]);
    }
});

$scheduler->task('updates.check', 'daily_at:02:30', function () {
    if (class_exists(\App\Services\Updater\UpdateChecker::class) && setting('update_auto_check', '1') === '1') {
        try {
            \App\Services\Updater\UpdateChecker::checkAndNotify();
        } catch (\Throwable $e) {
            Logger::channel('update')->error('Scheduled update check failed', ['error' => $e->getMessage()]);
        }
    }
});

$scheduler->task('backups.daily', 'daily_at:03:30', function () {
    if (class_exists(\App\Services\Updater\BackupService::class) && setting('backup_daily', '0') === '1') {
        \App\Services\Updater\BackupService::databaseBackup('scheduled');
    }
});

$scheduler->task('session_windows.expire', 'every_5_minutes', function () {
    // Notify UI about conversations whose 24h window just closed
    DB::table('conversations')
        ->where('session_open', 1)
        ->where('session_expires_at', '<', date('Y-m-d H:i:s'))
        ->update(['session_open' => 0]);
});

$scheduler->run();

// -- Cron-only queue fallback -----------------------------------------------
// If no long-running worker has reported a heartbeat recently, drain the
// queue inline (bounded) so shared-hosting installs still process jobs.
$workerHeartbeat = STORAGE_PATH . '/locks/worker_heartbeat';
$workerAlive = is_file($workerHeartbeat) && (time() - (int) @filemtime($workerHeartbeat)) < 120;

if (!$workerAlive && (bool) Config::get('queue.cron_fallback.enabled', true)) {
    $lock = Scheduler::acquireLock('cron_worker', 120);
    if ($lock !== null) {
        $deadline = time() + (int) Config::get('queue.cron_fallback.max_seconds', 50);
        $queues = (array) Config::get('queue.queues', ['default']);
        $workerId = 'cron-' . substr(sha1((string) getmypid() . microtime()), 0, 12);
        while (time() < $deadline) {
            if (is_file(STORAGE_PATH . '/maintenance.flag')) {
                break;
            }
            $worked = Queue::work($queues, $workerId);
            if (!$worked) {
                usleep(500000);
            }
        }
        Scheduler::releaseLock($lock);
    }
}

exit(0);
