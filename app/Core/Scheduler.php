<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cron task dispatcher. cron/scheduler.php calls Scheduler::run() every
 * minute. Tasks self-declare their cadence; overlap is prevented with
 * file locks. Also powers the cron-only fallback queue worker.
 */
final class Scheduler
{
    /** @var array<int, array{name:string, cadence:string, callback:callable}> */
    private array $tasks = [];

    public function task(string $name, string $cadence, callable $callback): void
    {
        $this->tasks[] = ['name' => $name, 'cadence' => $cadence, 'callback' => $callback];
    }

    public function run(): void
    {
        $lock = self::acquireLock('scheduler', 300);
        if ($lock === null) {
            return; // previous run still active
        }

        $now = time();
        foreach ($this->tasks as $task) {
            if (!self::isDue($task['cadence'], $now)) {
                continue;
            }
            $started = microtime(true);
            $status = 'success';
            $message = '';
            try {
                ($task['callback'])();
            } catch (\Throwable $e) {
                $status = 'error';
                $message = $e->getMessage();
                Logger::channel('queue')->error('Scheduled task failed: ' . $task['name'], ['error' => $e->getMessage()]);
            }
            try {
                DB::table('cron_logs')->insert([
                    'task' => $task['name'],
                    'status' => $status,
                    'message' => mb_substr($message, 0, 1000),
                    'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                    'ran_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable) {
                // cron_logs unavailable (mid-install) — non-fatal
            }
        }

        // Heartbeat for the installer's "Test Cron" button + health page
        @file_put_contents(STORAGE_PATH . '/locks/cron_heartbeat', (string) $now);

        self::releaseLock($lock);
    }

    /**
     * Cadence formats: "every_minute", "every_5_minutes", "every_30_minutes",
     * "hourly", "daily" (00:05), "daily_at:HH:MM", "weekly", "monthly".
     */
    public static function isDue(string $cadence, int $now): bool
    {
        $minute = (int) date('i', $now);
        $hour = (int) date('G', $now);
        $day = (int) date('j', $now);
        $weekday = (int) date('w', $now);

        if ($cadence === 'every_minute') {
            return true;
        }
        if (preg_match('/^every_(\d+)_minutes$/', $cadence, $m)) {
            return $minute % max(1, (int) $m[1]) === 0;
        }
        if ($cadence === 'hourly') {
            return $minute === 0;
        }
        if ($cadence === 'daily') {
            return $hour === 0 && $minute === 5;
        }
        if (preg_match('/^daily_at:(\d{1,2}):(\d{2})$/', $cadence, $m)) {
            return $hour === (int) $m[1] && $minute === (int) $m[2];
        }
        if ($cadence === 'weekly') {
            return $weekday === 0 && $hour === 1 && $minute === 0;
        }
        if ($cadence === 'monthly') {
            return $day === 1 && $hour === 1 && $minute === 30;
        }
        return false;
    }

    // -- Lock helpers ---------------------------------------------------------

    /**
     * Acquire a named lock. Returns lock path or null if held and fresh.
     */
    public static function acquireLock(string $name, int $staleSeconds): ?string
    {
        $dir = STORAGE_PATH . '/locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $name) . '.lock';

        if (is_file($path)) {
            $age = time() - (int) @filemtime($path);
            if ($age < $staleSeconds) {
                return null;
            }
            @unlink($path); // stale
        }

        $fp = @fopen($path, 'x');
        if ($fp === false) {
            return null;
        }
        fwrite($fp, (string) getmypid());
        fclose($fp);
        return $path;
    }

    public static function releaseLock(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function lastHeartbeat(): ?int
    {
        $file = STORAGE_PATH . '/locks/cron_heartbeat';
        if (!is_file($file)) {
            return null;
        }
        return (int) @file_get_contents($file);
    }
}
