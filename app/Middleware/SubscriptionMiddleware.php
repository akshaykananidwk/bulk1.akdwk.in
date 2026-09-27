<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

/**
 * Locks the tenant panel to the billing page once the trial/subscription has
 * expired. Only applied to the /tenant route group, so webhooks, the public
 * API, cron and the admin panel keep working. A super admin (including one
 * impersonating a tenant user) is never blocked.
 */
final class SubscriptionMiddleware
{
    private const ALLOWED_PREFIXES = ['/tenant/billing'];

    public function handle(Request $request, callable $next): void
    {
        $tenant = Tenant::current();
        if ($tenant === null || Auth::isSuperAdmin() || Auth::isImpersonating()) {
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
            // Team members without billing access cannot open the billing page
            if (!Auth::can('billing.view')) {
                Response::abort(402, $message . ' ' . __('billing.ask_owner', 'Please ask your workspace owner to renew.'));
            }
            // The billing page itself explains the expiry, so no extra flash
            Redirect::to('/tenant/billing')->send();
        }

        $next();
    }
}
