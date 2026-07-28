<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Two things live here:
 *  1. An in-process event bus (listeners from config/events.php).
 *  2. The persistent `events` table feed that powers SSE real-time.
 */
final class Event
{
    private static ?array $listeners = null;

    /**
     * Fire an in-process event to registered listeners.
     */
    public static function fire(string $event, array $payload = []): void
    {
        if (self::$listeners === null) {
            self::$listeners = Config::all('events');
        }
        foreach ((array) (self::$listeners[$event] ?? []) as $listener) {
            try {
                if (is_callable($listener)) {
                    $listener($payload);
                } elseif (is_string($listener) && class_exists($listener)) {
                    (new $listener())->handle($payload);
                }
            } catch (\Throwable $e) {
                Logger::channel('app')->error('Event listener failed: ' . $event, ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Publish a real-time event to the `events` table (consumed by SSE).
     * $channel examples: inbox, notifications, campaign, presence.
     */
    public static function publish(string $channel, string $type, array $payload = [], ?int $tenantId = null, ?int $userId = null): void
    {
        try {
            DB::table('events')->insert([
                'tenant_id' => $tenantId ?? Tenant::id(),
                'channel' => $channel,
                'user_id' => $userId,
                'type' => $type,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::channel('app')->error('Event publish failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Fetch real-time events after a cursor for the SSE stream.
     */
    public static function after(int $lastEventId, int $tenantId, array $channels, ?int $userId = null): array
    {
        $query = DB::table('events')
            ->where('id', '>', $lastEventId)
            ->where('tenant_id', $tenantId)
            ->whereIn('channel', $channels)
            ->orderBy('id', 'ASC')
            ->limit(100);

        $rows = $query->get();

        // user_id NULL = broadcast to all tenant users; otherwise targeted.
        return array_values(array_filter($rows, function (array $row) use ($userId) {
            return $row['user_id'] === null || $userId === null || (int) $row['user_id'] === $userId;
        }));
    }

    /**
     * Prune events older than 24 hours (scheduler task).
     */
    public static function prune(): int
    {
        return DB::table('events')
            ->where('created_at', '<', date('Y-m-d H:i:s', time() - 86400))
            ->delete();
    }
}
