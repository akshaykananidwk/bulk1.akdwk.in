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

        $trialEndsAt = $tenant['trial_ends_at'] ?? null;
        $subscriptionEndsAt = $tenant['subscription_ends_at'] ?? null;
        $now = date('Y-m-d H:i:s');

        $active = ($subscriptionEndsAt !== null && $subscriptionEndsAt > $now)
            || ($trialEndsAt !== null && $trialEndsAt > $now);

        if (!$active) {
            if ($request->wantsJson()) {
                Response::json(['success' => false, 'message' => __('billing.expired', 'Your subscription has expired. Please renew to continue.')], 402);
            }
            Redirect::to('/tenant/billing')->with('warning', __('billing.expired', 'Your subscription has expired. Please renew to continue.'))->send();
        }

        $next();
    }
}
