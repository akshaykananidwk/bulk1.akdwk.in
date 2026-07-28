<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Permission gate: route middleware "permission:contacts.view".
 */
final class PermissionMiddleware
{
    public function handle(Request $request, callable $next, string $permission = ''): void
    {
        if ($permission === '' || !Auth::can($permission)) {
            Response::abort(403, __('auth.no_permission', 'You do not have permission for this action.'));
        }
        $next();
    }
}
