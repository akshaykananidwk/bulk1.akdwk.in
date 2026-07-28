<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

/**
 * CSRF verification for all state-changing requests.
 * API routes use token auth and are exempt (registered without this middleware).
 */
final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (!Csrf::verify($request)) {
                if ($request->wantsJson()) {
                    Response::json(['success' => false, 'message' => __('auth.csrf', 'Session expired. Refresh and try again.')], 419);
                }
                Response::abort(419, __('auth.csrf', 'Session expired. Refresh and try again.'));
            }
        }
        $next();
    }
}
