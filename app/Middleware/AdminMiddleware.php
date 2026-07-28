<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Super-admin area gate, with optional IP allowlist from settings.
 */
final class AdminMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        if (!Auth::isSuperAdmin()) {
            Response::abort(403, __('auth.forbidden', 'You do not have access to this area.'));
        }

        $allowlist = (string) setting('admin_ip_allowlist', '');
        if ($allowlist !== '') {
            $ips = array_filter(array_map('trim', explode(',', $allowlist)));
            if ($ips && !in_array($request->ip(), $ips, true)) {
                Response::abort(403, __('auth.ip_blocked', 'Admin access is not allowed from this IP.'));
            }
        }

        $next();
    }
}
