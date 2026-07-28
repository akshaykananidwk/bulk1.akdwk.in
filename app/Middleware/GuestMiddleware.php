<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;

/**
 * Only for guests (login/register pages) — authenticated users are
 * bounced to their dashboard.
 */
final class GuestMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        if (Auth::check()) {
            Redirect::to(Auth::isSuperAdmin() ? '/admin' : '/tenant')->send();
        }
        $next();
    }
}
