<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fixed-window rate limiter backed by Cache::increment().
 */
final class RateLimiter
{
    /**
     * Attempt an action. Returns true if allowed, false if limited.
     */
    public static function attempt(string $key, int $maxAttempts, int $windowSeconds = 60): bool
    {
        $count = Cache::increment('ratelimit:' . $key, $windowSeconds);
        return $count <= $maxAttempts;
    }

    public static function tooManyAttempts(string $key, int $maxAttempts, int $windowSeconds = 60): bool
    {
        return !self::attempt($key, $maxAttempts, $windowSeconds);
    }

    public static function clear(string $key): void
    {
        Cache::forget('ratelimit:' . $key);
    }

    /**
     * Throttle helper for login-style flows with lockout.
     */
    public static function hit(string $key, int $windowSeconds = 900): int
    {
        return Cache::increment('ratelimit:' . $key, $windowSeconds);
    }
}
