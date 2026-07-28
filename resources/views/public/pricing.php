<?php use App\Core\View; ?>
<?php View::start('content'); ?>
<style>
    .pricing-wrap { max-width: 1080px; margin: 0 auto; padding: 3rem 1.25rem; }
    .price-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-top: 2rem; }
    .price-card.featured { border-color: var(--primary); box-shadow: 0 8px 32px rgba(15,118,110,.18); }
    .compare { margin-top: 3rem; }
</style>
<div class="pricing-wrap">
    <h1 class="text-center" style="font-size:2rem"><?= e(__('site.pricing_title', 'Simple pricing')) ?></h1>
    <p class="text-center text-muted"><?= e(__('site.pricing_page_lead', 'Every plan includes the team inbox, campaigns, chatbots, contacts CRM, templates and the full REST API. Start with a free trial — no credit card required.')) ?></p>

    <div class="price-grid">
        <?php foreach ($plans as $plan): ?>
            <div class="card price-card <?= $plan['is_featured'] ? 'featured' : '' ?>">
                <?php if ($plan['is_featured']): ?><span class="badge badge-warning">★ <?= e(__('site.popular', 'Most popular')) ?></span><?php endif; ?>
                <h3 style="margin-top:.4rem"><?= e($plan['name']) ?></h3>
                <div class="stat-value" style="margin:.3rem 0">
                    <?= (float) $plan['price_monthly'] > 0 ? e(format_money((float) $plan['price_monthly'])) : e(__('site.free', 'Free')) ?>
                    <?php if ((float) $plan['price_monthly'] > 0): ?><span class="text-sm text-muted">/<?= e(__('billing.month', 'month')) ?></span><?php endif; ?>
                </div>
                <?php if ((float) $plan['price_yearly'] > 0): ?>
                    <div class="text-xs text-muted"><?= e(format_money((float) $plan['price_yearly'])) ?>/<?= e(__('site.year', 'year')) ?> <span class="badge badge-success"><?= e(__('site.save2mo', '2 months free')) ?></span></div>
                <?php endif; ?>
                <p class="text-muted text-sm mt-2"><?= e($plan['description'] ?? '') ?></p>
                <?php $f = $planFeatures[(int) $plan['id']] ?? []; ?>
                <?php
                $rows = [
                    ['contacts', __('site.contacts', 'contacts')],
                    ['messages_monthly', __('site.messages_mo', 'messages/month')],
                    ['agents', __('site.agents', 'team members')],
                    ['waba_numbers', __('site.numbers', 'WhatsApp numbers')],
                    ['campaigns_monthly', __('site.campaigns_mo', 'campaigns/month')],
                    ['bot_flows', __('site.flows', 'bot flows')],
                    ['storage_mb', __('site.storage', 'MB storage')],
                ];
                ?>
                <ul class="text-sm" style="padding-left:1.1rem; line-height:2.05">
                    <?php foreach ($rows as [$key, $label]): ?>
                        <li>
                            <?php $value = $f[$key] ?? null; ?>
                            <?= $value === '-1' || $value === null ? e(__('site.unlimited', 'Unlimited')) : e(number_format((float) $value)) ?>
                            <?= e($label) ?>
                        </li>
                    <?php endforeach; ?>
                    <li>✅ <?= e(__('site.inc_api', 'REST API + webhooks')) ?></li>
                    <li>✅ <?= e(__('site.inc_inbox', 'Team inbox + chatbots')) ?></li>
                </ul>
                <a class="btn <?= $plan['is_featured'] ? 'btn-primary' : 'btn-outline' ?> btn-block" href="<?= e(url('/register')) ?>"><?= e(__('site.choose_plan', 'Get started')) ?></a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card compare">
        <h3 class="card-title">💡 <?= e(__('site.meta_fees_title', 'About WhatsApp conversation fees')) ?></h3>
        <p class="text-sm text-muted" style="margin:0">
            <?= e(__('site.meta_fees_body', 'Meta charges a small per-conversation/per-message fee for WhatsApp Business API traffic (marketing, utility and authentication messages; customer-service replies within the 24-hour window are free). These fees are set by Meta, vary by country, and are billed transparently per message inside the app — you always see the exact cost of every campaign before and after sending.')) ?>
        </p>
    </div>

    <div class="card mt-4">
        <h3 class="card-title">❓ <?= e(__('site.faq_title', 'Common questions')) ?></h3>
        <details class="mb-2"><summary class="font-semi"><?= e(__('site.faq1_q', 'Do I need my own WhatsApp number?')) ?></summary>
            <p class="text-sm text-muted mt-1"><?= e(__('site.faq1_a', 'Yes — you connect your own number via the official Meta Embedded Signup (a number not currently registered on the WhatsApp mobile app, or one you are ready to migrate). Your number, your account, your data.')) ?></p></details>
        <details class="mb-2"><summary class="font-semi"><?= e(__('site.faq2_q', 'Can I cancel anytime?')) ?></summary>
            <p class="text-sm text-muted mt-1"><?= e(__('site.faq2_a', 'Yes. Cancel from Billing whenever you like — the plan stays active until the end of the paid period. See our Refund & Cancellation Policy for details.')) ?></p></details>
        <details class="mb-2"><summary class="font-semi"><?= e(__('site.faq3_q', 'Is bulk messaging allowed?')) ?></summary>
            <p class="text-sm text-muted mt-1"><?= e(__('site.faq3_a', 'Broadcasts use Meta-approved templates and require recipient opt-in. The platform enforces opt-outs (STOP), throttling and frequency caps so your number quality stays healthy.')) ?></p></details>
        <details><summary class="font-semi"><?= e(__('site.faq4_q', 'Do you offer GST invoices?')) ?></summary>
            <p class="text-sm text-muted mt-1"><?= e(__('site.faq4_a', 'Yes — add your GSTIN in Settings and every invoice is GST-compliant with CGST/SGST/IGST breakup.')) ?></p></details>
    </div>
</div>
<?php View::end(); ?>
