<?php
/**
 * Billing-state badge for a tenant row. Expects: $tenant (needs trial_ends_at,
 * subscription_ends_at). Renders nothing for an active trial/subscription.
 */
$billingState = \App\Models\Tenant::billingState($tenant);
?>
<?php if ($billingState === 'trial_expired'): ?>
    <span class="badge badge-danger" title="<?= e(__('admin.trial_expired_hint', 'Trial ended — the tenant is locked to the billing page')) ?>"><?= e(__('admin.trial_expired', 'Trial expired')) ?></span>
<?php elseif ($billingState === 'expired'): ?>
    <span class="badge badge-danger" title="<?= e(__('admin.sub_expired_hint', 'Subscription ended — the tenant is locked to the billing page')) ?>"><?= e(__('admin.sub_expired', 'Subscription expired')) ?></span>
<?php endif; ?>
