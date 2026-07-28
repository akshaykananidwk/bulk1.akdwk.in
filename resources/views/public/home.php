<?php
use App\Core\View;

$appName = (string) setting('app_name', 'Krishna WhatsApp Cloud');
?>
<?php View::start('content'); ?>
<style>
    .hero {
        text-align: center; padding: 5rem 1.25rem 3.5rem;
        background:
            radial-gradient(900px 420px at 15% -10%, rgba(15,118,110,.14), transparent 60%),
            radial-gradient(700px 380px at 95% 0%, rgba(245,158,11,.12), transparent 55%);
    }
    .hero h1 { font-size: clamp(1.8rem, 4.5vw, 3rem); max-width: 780px; margin: 0 auto .8rem; line-height: 1.2; }
    .hero .sub { font-size: 1.08rem; color: var(--text-muted); max-width: 620px; margin: 0 auto 1.6rem; }
    .hero .badges { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; margin-top: 1.6rem; }
    .section { max-width: 1080px; margin: 0 auto; padding: 3rem 1.25rem; }
    .section h2 { text-align: center; font-size: 1.6rem; margin-bottom: .4rem; }
    .section .lead { text-align: center; color: var(--text-muted); margin-bottom: 2rem; }
    .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
    .feature-card .icon { font-size: 1.8rem; }
    .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; counter-reset: step; }
    .step { position: relative; padding-top: .5rem; }
    .step::before {
        counter-increment: step; content: counter(step);
        display: inline-flex; width: 34px; height: 34px; align-items: center; justify-content: center;
        background: var(--primary); color: #fff; font-weight: 700; border-radius: 50%; margin-bottom: .6rem;
    }
    .price-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; }
    .price-card.featured { border-color: var(--primary); box-shadow: 0 8px 32px rgba(15,118,110,.18); }
    .cta-band {
        max-width: 1080px; margin: 2rem auto 0; padding: 3rem 1.5rem; text-align: center;
        background: linear-gradient(120deg, var(--primary), #0d9488); color: #fff; border-radius: 20px;
    }
    .cta-band h2 { color: #fff; }
</style>

<!-- Hero -->
<section class="hero">
    <span class="badge badge-primary" style="margin-bottom:1rem">🇮🇳 <?= e(__('site.hero_badge', 'Built for Indian businesses · Gujarati + Hindi + English')) ?></span>
    <h1><?= e(__('site.hero_title', 'Grow your business on WhatsApp — inbox, broadcasts & chatbots on your own number')) ?></h1>
    <p class="sub"><?= e(__('site.hero_sub', 'Official WhatsApp Business Cloud API. One shared team inbox, bulk campaigns with real delivery reports, no-code chatbots and a full REST API — self-hosted, your data stays with you.')) ?></p>
    <div class="flex gap-2" style="justify-content:center">
        <?php if (setting('registration_enabled', '1') === '1'): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('/register')) ?>">🚀 <?= e(__('site.hero_cta', 'Start free trial')) ?></a>
        <?php endif; ?>
        <a class="btn btn-outline btn-lg" href="<?= e(url('/pricing')) ?>"><?= e(__('site.see_pricing', 'See pricing')) ?></a>
    </div>
    <div class="badges">
        <span class="badge badge-muted">✅ <?= e(__('site.badge_official', 'Official Meta Cloud API')) ?></span>
        <span class="badge badge-muted">🔒 <?= e(__('site.badge_data', 'Your data, your server')) ?></span>
        <span class="badge badge-muted">💳 <?= e(__('site.badge_gst', 'GST invoices')) ?></span>
        <span class="badge badge-muted">🧑‍💻 <?= e(__('site.badge_api', 'REST API on every plan')) ?></span>
    </div>
</section>

<!-- Features -->
<section class="section" id="features">
    <h2><?= e(__('site.features_title', 'Everything your team needs on WhatsApp')) ?></h2>
    <p class="lead"><?= e(__('site.features_lead', 'From first hello to repeat order — one platform.')) ?></p>
    <div class="feature-grid">
        <div class="card feature-card">
            <div class="icon">💬</div>
            <h3><?= e(__('site.f_inbox', 'Shared Team Inbox')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.f_inbox_d', 'Every agent on one WhatsApp number. Assign chats, private notes with @mentions, quick replies, live typing — with a 24-hour session timer on every conversation.')) ?></p>
        </div>
        <div class="card feature-card">
            <div class="icon">📣</div>
            <h3><?= e(__('site.f_campaigns', 'Broadcast Campaigns')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.f_campaigns_d', 'Approved-template broadcasts to groups, tags and smart segments. Throttling, opt-out compliance, per-message cost and delivery/read reports built in.')) ?></p>
        </div>
        <div class="card feature-card">
            <div class="icon">🤖</div>
            <h3><?= e(__('site.f_bots', 'Chatbots & Automation')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.f_bots_d', 'Keyword triggers, buttons and lists, conditions, delays that survive restarts, OTP flows, order lookups and human handover — all running server-side.')) ?></p>
        </div>
        <div class="card feature-card">
            <div class="icon">👥</div>
            <h3><?= e(__('site.f_crm', 'Contacts CRM')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.f_crm_d', 'Custom fields, tags, lifecycle stages, lead scores, CSV import with duplicate detection, and automatic STOP/START consent tracking.')) ?></p>
        </div>
        <div class="card feature-card">
            <div class="icon">📋</div>
            <h3><?= e(__('site.f_templates', 'Template Manager')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.f_templates_d', 'Create, submit and sync Meta message templates with a live WhatsApp-style preview and variable mapping from contact fields.')) ?></p>
        </div>
        <div class="card feature-card">
            <div class="icon">🔌</div>
            <h3><?= e(__('site.f_api', 'REST API & Webhooks')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.f_api_d', 'Scoped API keys, self-hosted interactive docs, and HMAC-signed webhooks for every message event — connect anything.')) ?></p>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="section">
    <h2><?= e(__('site.how_title', 'Live in 10 minutes')) ?></h2>
    <p class="lead"><?= e(__('site.how_lead', 'No developers needed to get started.')) ?></p>
    <div class="steps">
        <div class="card step">
            <h3><?= e(__('site.step1', 'Create your workspace')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.step1_d', 'Free trial, no credit card. Your team, roles and permissions ready out of the box.')) ?></p>
        </div>
        <div class="card step">
            <h3><?= e(__('site.step2', 'Connect WhatsApp')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.step2_d', 'One-click Meta Embedded Signup with your own number — or paste an existing API token.')) ?></p>
        </div>
        <div class="card step">
            <h3><?= e(__('site.step3', 'Import contacts')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.step3_d', 'Upload your CSV — duplicates and opt-outs handled automatically.')) ?></p>
        </div>
        <div class="card step">
            <h3><?= e(__('site.step4', 'Chat, broadcast, automate')) ?></h3>
            <p class="text-muted text-sm"><?= e(__('site.step4_d', 'Reply from the team inbox, launch your first campaign, switch on a welcome bot.')) ?></p>
        </div>
    </div>
</section>

<!-- Pricing preview -->
<?php if (!empty($plans)): ?>
<section class="section" id="pricing">
    <h2><?= e(__('site.pricing_title', 'Simple pricing')) ?></h2>
    <p class="lead"><?= e(__('site.pricing_lead', 'Start free. Upgrade when you grow. WhatsApp conversation fees from Meta are separate and shown transparently per message.')) ?></p>
    <div class="price-grid">
        <?php foreach ($plans as $plan): ?>
            <div class="card price-card <?= $plan['is_featured'] ? 'featured' : '' ?>">
                <?php if ($plan['is_featured']): ?><span class="badge badge-warning">★ <?= e(__('site.popular', 'Most popular')) ?></span><?php endif; ?>
                <h3 style="margin-top:.4rem"><?= e($plan['name']) ?></h3>
                <div class="stat-value" style="margin:.3rem 0">
                    <?= (float) $plan['price_monthly'] > 0 ? e(format_money((float) $plan['price_monthly'])) : e(__('site.free', 'Free')) ?>
                    <?php if ((float) $plan['price_monthly'] > 0): ?><span class="text-sm text-muted">/<?= e(__('billing.month', 'month')) ?></span><?php endif; ?>
                </div>
                <p class="text-muted text-sm"><?= e($plan['description'] ?? '') ?></p>
                <?php $f = $planFeatures[(int) $plan['id']] ?? []; ?>
                <ul class="text-sm" style="padding-left:1.1rem; line-height:2">
                    <li><?= ($f['contacts'] ?? '') === '-1' ? e(__('site.unlimited', 'Unlimited')) : e(number_format((float) ($f['contacts'] ?? 0))) ?> <?= e(__('site.contacts', 'contacts')) ?></li>
                    <li><?= ($f['messages_monthly'] ?? '') === '-1' ? e(__('site.unlimited', 'Unlimited')) : e(number_format((float) ($f['messages_monthly'] ?? 0))) ?> <?= e(__('site.messages_mo', 'messages/month')) ?></li>
                    <li><?= ($f['agents'] ?? '') === '-1' ? e(__('site.unlimited', 'Unlimited')) : e((string) ($f['agents'] ?? 0)) ?> <?= e(__('site.agents', 'team members')) ?></li>
                    <li><?= ($f['bot_flows'] ?? '') === '-1' ? e(__('site.unlimited', 'Unlimited')) : e((string) ($f['bot_flows'] ?? 0)) ?> <?= e(__('site.flows', 'bot flows')) ?></li>
                </ul>
                <a class="btn <?= $plan['is_featured'] ? 'btn-primary' : 'btn-outline' ?> btn-block" href="<?= e(url('/register')) ?>"><?= e(__('site.choose_plan', 'Get started')) ?></a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- CTA -->
<section style="padding: 0 1.25rem">
    <div class="cta-band">
        <h2><?= e(__('site.cta_title', 'Ready to talk to your customers where they already are?')) ?></h2>
        <p style="opacity:.9"><?= e(__('site.cta_sub', 'Join businesses using :app for WhatsApp-first growth.', ['app' => $appName])) ?></p>
        <a class="btn btn-accent btn-lg" href="<?= e(url('/register')) ?>">🚀 <?= e(__('site.hero_cta', 'Start free trial')) ?></a>
    </div>
</section>
<?php View::end(); ?>
