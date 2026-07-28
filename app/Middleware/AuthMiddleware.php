<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;

/**
 * Requires an authenticated session; redirects guests to login.
 */
final class AuthMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                Response::json(['success' => false, 'message' => __('auth.unauthenticated', 'Please log in.')], 401);
            }
            $_SESSION['intended_url'] = $request->fullUrl();
            Redirect::to('/login')->send();
        }
        $next();
    }
}
