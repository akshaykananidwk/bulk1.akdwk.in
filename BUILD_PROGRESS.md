# Build Progress

Honest per-phase status. ✅ complete & tested · 🟡 partial (working core, spec breadth remaining) · ⬜ schema-ready, not yet built.

| Phase | Scope | Status | Verified by |
|---|---|---|---|
| 0 | Core framework (router, DB/builder, queue, scheduler, SSE, crypt, mail, validator, cache, i18n, middleware, cron entrypoints) | ✅ | `php -l` 100%, live requests |
| 1 | Schema (119 tables), ordered seeds, base models | ✅ | Imported clean on MariaDB 10.11; role grants verified |
| 2 | Auth (login/register/forgot/reset/2FA TOTP+backup codes), RBAC, tenancy, layouts, dashboards, en/gu/hi | ✅ | Live: login→dashboard, register→workspace+roles+wallet, CSRF 419 |
| 3 | Meta Cloud API (client, webhook verify+HMAC, processor for all inbound types & statuses+pricing, sender for every §5.3 type, session window, error mapper, templates, media, embedded signup + manual connect) | ✅ | Live simulated signed webhooks: message stored, window opened, status+pricing applied, duplicates idempotent |
| 4 | Team inbox (3-pane, filters/search/cursor pagination, send text/media/template, notes+@mentions, assign, status/star/pin/archive, typing, read receipts, session countdown, SSE) | ✅ | Live endpoints; cross-tenant access 404 |
| 5 | Templates CRUD + Meta sync + WhatsApp-accurate preview + variable mapping | ✅ | Sync/upsert logic unit-run; UI renders (real Meta sync needs a live WABA) |
| 6 | Contacts CRM (custom fields, tags, STOP/START compliance, CSV import with mapping+dedupe, export, FULLTEXT search) | ✅ | Live pages; import/export code paths |
| 6 | Campaigns (audience groups/tags/segments, throttle, frequency caps, opt-out exclusion, pause/resume/cancel, live progress, cost) | ✅ | Engine test: audience 5/6 (opt-out excluded), throttle 3/tick, pause blocks dispatch |
| 7 | Flow engine (server-side, resumable) + 25+ node types + keyword/any-message/new-contact/order/form triggers + JSON editor + versions + run logs | ✅ engine / 🟡 visual builder (JSON editor now; Drawflow canvas vendored, not wired) | Engine test: variables, condition branch, tag, delay survived restart, logs |
| 7 | A/B variants, recurring campaigns, timezone-aware sends | ⬜ schema-ready (`ab_variants`, `campaigns.recurring/timezone_aware`) | — |
| 8 | AI module (providers, RAG, token metering) | ⬜ schema-ready (9 tables); no service layer yet | — |
| 8 | Marketing: QR redirect + scan analytics, lead forms (public renderer+submit+contact mapping+flow trigger), landing page renderer | ✅ core / 🟡 builder UIs (create/manage screens pending; render+submit paths complete) | Live public routes |
| 8 | E-commerce: order/cart webhooks (Shopify/Woo/custom HMAC), order-event flows, abandoned-cart status tracking | 🟡 inbound sync done; outbound store-API drivers + catalog sync pending | Controller complete, signature-verified |
| 9 | Payments: Razorpay/Stripe/Cashfree webhooks (signature+idempotency+wallet credit+subscription activation), GST fields, wallet ledger | 🟡 webhook side done; checkout UI + invoice PDF pending | Controller complete |
| 10 | REST API v1 (messages/contacts/templates/conversations), API keys with scopes+rate limits, OpenAPI 3.1 + self-hosted docs, outbound webhooks with HMAC+retries+circuit breaker, Zapier REST hooks | ✅ | Live /api/docs + /api/openapi.json |
| 11 | Admin panel (tenants CRUD+suspend+trial+wallet+plan+impersonate, plans builder, transactions, pricing rates, queue monitor, logs, health, backups, global settings, Meta app) | ✅ | All 14 admin pages render 200 |
| 11 | Reports module (12 report types, scheduled email delivery), support tickets UI, announcements UI | ⬜ schema-ready; stats_daily aggregation job runs | — |
| 12 | One-click installer (8 steps, self-locking, repair mode, cron-only fallback) | ✅ | Full HTTP install on empty DB: 119 tables, config generated, installer renamed, admin login OK |
| 13 | GitHub auto-updater (check card, 11-step update, zip-slip guard, protected paths, pure-PHP mysqldump, auto+manual rollback, integrity checker) | ✅ | Security tests: traversal/absolute/symlink rejected; config untouched; delete[] guarded; DB+files restore round-trips exact |
| 14 | Docs + verification | ✅ this file + VERIFICATION.md | — |

## Remaining work queue (next build passes)

1. AI module: `AiRouter` + OpenAI/Gemini/Claude/DeepSeek/Groq/Ollama drivers, FULLTEXT+cosine RAG, token metering guards, inbox AI suggestions.
2. Visual Drawflow canvas on top of the existing flow JSON format (+ simulator, template gallery).
3. Payment checkout flows (Razorpay/Stripe hosted pages), GST invoice PDF, dunning.
4. E-commerce outbound drivers (Shopify/Woo REST sync, catalog → Commerce Manager).
5. Reports UI (builder + CSV/Excel/PDF export + scheduled email), tickets, announcements.
6. PWA (manifest + service worker + push), landing/form/QR builder UIs, white-label theming UI.
7. A/B testing, recurring/timezone campaigns, SLA timers UI, passkeys (schema ready).
