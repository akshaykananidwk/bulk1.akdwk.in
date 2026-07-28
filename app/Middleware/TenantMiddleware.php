<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

/**
 * Requires tenant context and an active (non-suspended) tenant.
 * Binds the authenticated user's tenant when none resolved from domain.
 */
final class TenantMiddleware
{
    public function handle(Request $request, callable $next): void
    {
        $user = Auth::user();
        if ($user === null) {
            Redirect::to('/login')->send();
        }

        if (Tenant::id() === null && !empty($user['tenant_id'])) {
            Tenant::setId((int) $user['tenant_id']);
        }

        $tenant = Tenant::current();
        if ($tenant === null) {
            Response::abort(403, __('tenant.none', 'No workspace found for your account.'));
        }

        // A user may only operate inside their own tenant (super admin excepted)
        if (!Auth::isSuperAdmin() && (int) $user['tenant_id'] !== (int) $tenant['id']) {
            Response::abort(403);
        }

        if (($tenant['status'] ?? '') === 'suspended') {
            Response::abort(403, __('tenant.suspended', 'This workspace is suspended. Contact support.'));
        }

        $next();
    }
}
