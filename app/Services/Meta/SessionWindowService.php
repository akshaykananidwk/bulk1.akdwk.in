<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Core\DB;

/**
 * Enforces the 24-hour customer-service window:
 *  - inside the window  → any message type allowed
 *  - outside the window → only approved templates
 */
final class SessionWindowService
{
    public static function isOpen(array $conversation): bool
    {
        $expires = $conversation['session_expires_at'] ?? null;
        if ($expires === null) {
            return false;
        }
        return strtotime((string) $expires) > time();
    }

    public static function secondsRemaining(array $conversation): int
    {
        $expires = $conversation['session_expires_at'] ?? null;
        if ($expires === null) {
            return 0;
        }
        return max(0, strtotime((string) $expires) - time());
    }

    /**
     * Called on every inbound message: extends the window to now + 24h.
     */
    public static function touchInbound(int $conversationId): string
    {
        $hours = (int) config('meta.session_window_hours', 24);
        $expiresAt = date('Y-m-d H:i:s', time() + $hours * 3600);
        DB::table('conversations')->where('id', $conversationId)->update([
            'last_inbound_at' => now(),
            'session_expires_at' => $expiresAt,
            'session_open' => 1,
        ]);
        return $expiresAt;
    }

    /**
     * Guard for free-form sends. Throws with a friendly message when the
     * window is closed and the message is not a template.
     */
    public static function assertCanSend(array $conversation, string $type): void
    {
        if ($type === 'template') {
            return;
        }
        if (!self::isOpen($conversation)) {
            throw new \RuntimeException(__(
                'inbox.window_closed',
                'The 24-hour session window has expired. Only approved templates can be sent until the customer replies.'
            ));
        }
    }
}
