<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Database-backed job queue. No Redis required.
 *
 * Pop strategy: FOR UPDATE SKIP LOCKED on MySQL 8 / MariaDB 10.6+,
 * atomic UPDATE-claim fallback for older servers.
 */
final class Queue
{
    public const BACKOFF = [10, 60, 300];

    /**
     * Push a job. $jobClass must implement App\Jobs\JobInterface.
     */
    public static function push(string $jobClass, array $payload = [], string $queue = 'default', int $priority = 5, int $delaySeconds = 0, ?int $tenantId = null): int
    {
        return DB::table('jobs')->insert([
            'tenant_id' => $tenantId ?? Tenant::id(),
            'queue' => $queue,
            'job_class' => $jobClass,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'priority' => $priority,
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => time() + max(0, $delaySeconds),
            'reserved_at' => null,
            'reserved_by' => null,
            'created_at' => time(),
        ]);
    }

    /**
     * Reserve and return the next job for the given queues, or null.
     */
    public static function pop(array $queues, string $workerId): ?array
    {
        foreach ($queues as $queue) {
            $job = DB::supportsSkipLocked()
                ? self::popSkipLocked($queue, $workerId)
                : self::popLegacy($queue, $workerId);
            if ($job !== null) {
                return $job;
            }
        }
        return null;
    }

    private static function popSkipLocked(string $queue, string $workerId): ?array
    {
        return DB::transaction(function () use ($queue, $workerId) {
            $prefix = DB::prefix();
            $row = DB::selectOne(
                'SELECT * FROM `' . $prefix . 'jobs` WHERE `queue` = ? AND `reserved_at` IS NULL AND `available_at` <= ? '
                . 'ORDER BY `priority` ASC, `id` ASC LIMIT 1 FOR UPDATE SKIP LOCKED',
                [$queue, time()]
            );
            if ($row === null) {
                return null;
            }
            DB::table('jobs')->where('id', $row['id'])->update([
                'reserved_at' => time(),
                'reserved_by' => $workerId,
                'attempts' => (int) $row['attempts'] + 1,
            ]);
            $row['attempts'] = (int) $row['attempts'] + 1;
            return $row;
        });
    }

    private static function popLegacy(string $queue, string $workerId): ?array
    {
        // Atomic claim: UPDATE first, then SELECT what we claimed.
        $prefix = DB::prefix();
        $claimed = DB::statement(
            'UPDATE `' . $prefix . 'jobs` SET `reserved_at` = ?, `reserved_by` = ?, `attempts` = `attempts` + 1 '
            . 'WHERE `queue` = ? AND `reserved_at` IS NULL AND `available_at` <= ? '
            . 'ORDER BY `priority` ASC, `id` ASC LIMIT 1',
            [time(), $workerId, $queue, time()]
        );
        if ($claimed === 0) {
            return null;
        }
        return DB::table('jobs')->where('reserved_by', $workerId)->whereNotNull('reserved_at')->orderBy('reserved_at', 'DESC')->first();
    }

    /**
     * Delete a completed job.
     */
    public static function complete(array $job): void
    {
        DB::table('jobs')->where('id', $job['id'])->delete();
    }

    /**
     * Handle a failed attempt: retry with backoff or move to failed_jobs.
     */
    public static function fail(array $job, \Throwable $e): void
    {
        $attempts = (int) $job['attempts'];
        $maxAttempts = (int) $job['max_attempts'];

        if ($attempts < $maxAttempts) {
            $backoff = self::BACKOFF[min($attempts - 1, count(self::BACKOFF) - 1)];
            DB::table('jobs')->where('id', $job['id'])->update([
                'reserved_at' => null,
                'reserved_by' => null,
                'available_at' => time() + $backoff,
            ]);
            Logger::channel('queue')->warning('Job retry scheduled', [
                'id' => $job['id'],
                'class' => $job['job_class'],
                'attempt' => $attempts,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        DB::transaction(function () use ($job, $e) {
            DB::table('failed_jobs')->insert([
                'tenant_id' => $job['tenant_id'],
                'queue' => $job['queue'],
                'job_class' => $job['job_class'],
                'payload' => $job['payload'],
                'exception' => Logger::redact(mb_substr($e->getMessage() . "\n" . $e->getTraceAsString(), 0, 20000)),
                'failed_at' => date('Y-m-d H:i:s'),
            ]);
            DB::table('jobs')->where('id', $job['id'])->delete();
        });

        Logger::channel('queue')->error('Job failed permanently', [
            'id' => $job['id'],
            'class' => $job['job_class'],
            'error' => $e->getMessage(),
        ]);

        Event::fire('job.failed', ['job' => $job, 'error' => $e->getMessage()]);
    }

    /**
     * Execute one job. Returns true if a job was processed.
     */
    public static function work(array $queues, string $workerId): bool
    {
        $job = self::pop($queues, $workerId);
        if ($job === null) {
            return false;
        }

        try {
            $class = (string) $job['job_class'];
            if (!class_exists($class) || !is_subclass_of($class, \App\Jobs\JobInterface::class)) {
                throw new \RuntimeException('Unknown job class: ' . $class);
            }
            $payload = json_decode((string) $job['payload'], true) ?: [];

            // Restore tenant context for the job run
            Tenant::setId($job['tenant_id'] !== null ? (int) $job['tenant_id'] : null);

            $instance = new $class();
            $instance->handle($payload);

            self::complete($job);
        } catch (\Throwable $e) {
            self::fail($job, $e);
        } finally {
            Tenant::setId(null);
        }

        return true;
    }

    /**
     * Release jobs stuck in reserved state (worker died) after 15 minutes.
     */
    public static function releaseStuck(): int
    {
        return DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', time() - 900)
            ->update(['reserved_at' => null, 'reserved_by' => null]);
    }

    public static function retryFailed(?int $failedJobId = null): int
    {
        $query = DB::table('failed_jobs');
        if ($failedJobId !== null) {
            $query->where('id', $failedJobId);
        }
        $failed = $query->get();
        foreach ($failed as $job) {
            DB::table('jobs')->insert([
                'tenant_id' => $job['tenant_id'],
                'queue' => $job['queue'],
                'job_class' => $job['job_class'],
                'payload' => $job['payload'],
                'priority' => 5,
                'attempts' => 0,
                'max_attempts' => 3,
                'available_at' => time(),
                'created_at' => time(),
            ]);
            DB::table('failed_jobs')->where('id', $job['id'])->delete();
        }
        return count($failed);
    }

    public static function size(?string $queue = null): int
    {
        $query = DB::table('jobs');
        if ($queue !== null) {
            $query->where('queue', $queue);
        }
        return $query->count();
    }
}
