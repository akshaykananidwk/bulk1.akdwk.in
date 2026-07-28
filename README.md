# 🦚 Krishna WhatsApp Cloud

A self-hosted, **multi-tenant WhatsApp Business Cloud API platform** — an alternative to WATI / AiSensy / Interakt that runs on plain **PHP 8.3 + MySQL/MariaDB**. No Laravel, no Node.js, no Redis, no Docker required.

Part of the Krishna SaaS Suite (akdwk.in).

## Highlights

- **Official Meta WhatsApp Cloud API** — Embedded Signup + manual connection, all message types (text, media, interactive buttons/lists, CTA URL, WhatsApp Flows, products, templates), 24-hour session-window enforcement, per-message cost tracking with an admin-editable rate card
- **Multi-tenant SaaS** — plans with hard-enforced limits, trials, wallets, roles & permissions (cloned per workspace), impersonation with audit trail
- **Team Inbox** — three-pane live chat (SSE real-time, AJAX fallback), assignment, private notes with @mentions, quick replies, typing indicators, read receipts, session countdown, dark mode
- **Campaigns** — audience from groups/tags/segments, template variable mapping, per-minute throttling, frequency caps, opt-out compliance (STOP/START keywords), live progress and cost
- **Server-side resumable bot flows** — delays and waits survive restarts; conditions, branching, HTTP requests, OTP send/verify, order lookup, tagging, handover
- **One-click installer** — upload → `/install` → done; self-locks afterwards
- **GitHub auto-updater** — check + one-click update with pre-update backups (pure-PHP mysqldump), zip-slip protection, protected paths, and automatic rollback
- **REST API + outbound webhooks** — API keys with scopes and rate limits, self-hosted interactive docs, HMAC-signed webhooks
- **Gujarati / Hindi / English** UI out of the box

## Requirements

- PHP ≥ 8.3 with `pdo_mysql curl mbstring openssl zip gd fileinfo json`
- MySQL 8 / MariaDB 10.6+ (SKIP LOCKED queue; older servers use the fallback claim)
- Apache with mod_rewrite (aaPanel-friendly) or Nginx
- HTTPS (required by Meta webhooks)
- One cron entry (`* * * * * php cron/scheduler.php`) — PM2 optional for high volume

## Install

Upload the files, open `https://your-domain/install`, follow the 8-step wizard. See [INSTALL.md](INSTALL.md).

## Update

Admin → System → Updates → save your GitHub repo + token once → **Check for Update** → **Update Now**. See [UPDATE.md](UPDATE.md).

## Documentation

- [INSTALL.md](INSTALL.md) — installation & aaPanel deployment
- [UPDATE.md](UPDATE.md) — the GitHub auto-updater
- [DECISIONS.md](DECISIONS.md) — architecture decisions
- [BUILD_PROGRESS.md](BUILD_PROGRESS.md) — feature completion status
- `/api/docs` — interactive REST API documentation (self-hosted)

## Security notes

- All secrets (Meta tokens, gateway keys, SMTP password, GitHub token) stored AES-256-GCM encrypted with the installer-generated `APP_KEY`
- Prepared statements everywhere; identifier whitelisting in the query builder
- CSRF on all state-changing routes; per-route and per-key rate limits
- Webhook signature verification (Meta X-Hub-Signature-256, Razorpay/Stripe/Cashfree, Shopify/Woo HMAC)
- Uploads validated by extension + MIME + magic bytes; PHP execution denied in `uploads/`

## License

Proprietary — © Krishna SaaS Suite.
