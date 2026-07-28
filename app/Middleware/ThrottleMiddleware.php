<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

/**
 * Route throttle: "throttle:60,1" = 60 requests per 1 minute per IP+path.
 */
final class ThrottleMiddleware
{
    public function handle(Request $request, callable $next, string $maxAttempts = '60', string $minutes = '1'): void
    {
        $key = 'route:' . sha1($request->ip() . '|' . $request->path());
        $allowed = RateLimiter::attempt($key, max(1, (int) $maxAttempts), max(1, (int) $minutes) * 60);
        if (!$allowed) {
            if ($request->wantsJson()) {
                Response::json(['success' => false, 'message' => __('errors.rate_limited', 'Too many requests. Please slow down.')], 429);
            }
            Response::abort(429);
        }
        $next();
    }
}
