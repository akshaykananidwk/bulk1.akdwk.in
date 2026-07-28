<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Event;
use App\Core\Request;
use App\Core\Tenant;

/**
 * Real-time endpoints: SSE stream (primary) + AJAX polling (fallback)
 * + presence pings. See §3.4 of the platform design.
 */
final class SseController extends Controller
{
    public function stream(Request $request): never
    {
        $userId = (int) Auth::id();
        $user = Auth::user();
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId === 0) {
            \App\Core\Response::json(['success' => false], 403);
        }

        $channels = $this->channels($request);
        $lastEventId = (int) ($request->query('last_id')
            ?? $request->header('Last-Event-ID')
            ?? 0);

        // Release the session lock IMMEDIATELY so other requests are not blocked.
        session_write_close();

        // Concurrent connection cap per tenant to protect the FPM pool
        $cap = (int) config('app.sse.max_connections_per_tenant', 25);
        $connKey = 'sse_conn:' . $tenantId;
        $connections = Cache::increment($connKey, 40);
        if ($connections > $cap) {
            http_response_code(429);
            header('Content-Type: text/event-stream');
            echo "retry: 5000\n\n";
            exit;
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');
        @ini_set('output_buffering', '0');
        @ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        echo "retry: 2000\n\n";
        flush();

        $loopSeconds = (int) config('app.sse.loop_seconds', 30);
        $pollMicroseconds = ((int) config('app.sse.poll_interval_ms', 1500)) * 1000;
        $deadline = time() + max(5, min(60, $loopSeconds));

        while (time() < $deadline) {
            if (connection_aborted()) {
                break;
            }
            if (is_file(STORAGE_PATH . '/maintenance.flag')) {
                break;
            }

            try {
                $events = Event::after($lastEventId, $tenantId, $channels, $userId);
            } catch (\Throwable) {
                break;
            }

            foreach ($events as $event) {
                $lastEventId = max($lastEventId, (int) $event['id']);
                $data = json_encode([
                    'id' => (int) $event['id'],
                    'type' => $event['type'],
                    'payload' => json_decode((string) ($event['payload'] ?? 'null'), true),
                ], JSON_UNESCAPED_UNICODE);
                echo 'id: ' . $event['id'] . "\n";
                echo 'data: ' . $data . "\n\n";
            }
            if ($events) {
                flush();
            } else {
                // Heartbeat comment keeps proxies from buffering/closing
                echo ": hb\n\n";
                flush();
            }

            usleep($pollMicroseconds);
        }

        exit;
    }

    public function poll(Request $request): never
    {
        $user = Auth::user();
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId === 0) {
            $this->json(['events' => []]);
        }
        $channels = $this->channels($request);
        $lastEventId = (int) $request->query('last_id', 0);

        $events = Event::after($lastEventId, $tenantId, $channels, (int) Auth::id());
        $out = array_map(fn (array $event) => [
            'id' => (int) $event['id'],
            'type' => $event['type'],
            'payload' => json_decode((string) ($event['payload'] ?? 'null'), true),
        ], $events);

        $this->json(['events' => $out]);
    }

    public function presencePing(Request $request): never
    {
        $user = Auth::user();
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $userId = (int) Auth::id();
        if ($tenantId > 0) {
            $updated = DB::table('agent_presence')->where('user_id', $userId)->update([
                'status' => 'online',
                'last_seen_at' => now(),
            ]);
            if ($updated === 0) {
                try {
                    DB::table('agent_presence')->insert([
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'status' => 'online',
                        'last_seen_at' => now(),
                    ]);
                } catch (\Throwable) {
                    // concurrent insert — ignore
                }
            }
        }
        $this->ok();
    }

    private function channels(Request $request): array
    {
        $raw = (string) $request->query('channels', 'notifications');
        $channels = array_values(array_filter(array_map(
            fn (string $channel) => preg_replace('/[^a-z0-9_]/', '', trim($channel)),
            explode(',', $raw)
        )));
        return $channels ?: ['notifications'];
    }
}
