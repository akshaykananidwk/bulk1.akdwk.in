<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

/**
 * Blocks tenants whose trial/subscription has expired (except billing pages).
 */
final class SubscriptionMiddleware
{
    private const ALLOWED_PREFIXES = ['/tenant/billing', '/tenant/subscription', '/tenant/profile', '/logout'];

    public function handle(Request $request, callable $next): void
    {
        $tenant = Tenant::current();
        if ($tenant === null) {
            $next();
            return;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($request->path(), $prefix)) {
                $next();
                return;
            }
        }

        $state = \App\Models\Tenant::billingState($tenant);
        if ($state === 'trial_expired' || $state === 'expired') {
            $message = $state === 'trial_expired'
                ? __('billing.trial_expired', 'Your free trial has ended. Choose a plan to continue.')
                : __('billing.expired', 'Your subscription has expired. Please renew to continue.');
            if ($request->wantsJson()) {
                Response::json(['success' => false, 'message' => $message], 402);
            }
            Redirect::to('/tenant/billing')->with('warning', $message)->send();
        }

        $next();
    }
}
