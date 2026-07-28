# Architecture Decisions

Decisions made while building, per the master specification's rule 16.4
("make the best professional decision and note it").

## Core framework

1. **Config**: `config/config.php` (installer-generated) returns a nested array merged over shipped `config/*.php` defaults via `Config::get('group.key')`. No `.env` parser — one generated file, protected from the updater.
2. **Views are plain PHP** with a tiny section system (`View::start/end/yield_`). A view may define `content` via sections or via direct output — the layout handles both.
3. **Router**: segment-based regex compilation (literal, `{param}`, `{param?}`, embedded params, `where()` constraints). Middleware are classes resolved from a fixed alias map — no magic discovery.
4. **Query builder** whitelists identifiers (`[A-Za-z0-9_]`, dotted, `AS alias`) and throws on anything else; every value is bound. `DB::raw()`/`whereRaw()` accept only developer-authored fragments (grep-audited).
5. **Table prefix** is supported end-to-end (builder + schema importer + migrator apply it by rewriting statement table references). Default is empty; FK `REFERENCES` clauses are also rewritten.
6. **Queue** uses `FOR UPDATE SKIP LOCKED` when the server supports it (MySQL 8/MariaDB 10.6+), otherwise an atomic UPDATE-claim fallback. Cron-only mode drains the queue for ≤50s per minute inside `scheduler.php`, so shared hosting works with a single cron entry.
7. **Real-time**: SSE endpoint polls the `events` table on a 1.5s interval inside a ≤30s loop (then the browser reconnects with `Last-Event-ID`), with `session_write_close()` first and a per-tenant connection cap. Client auto-falls back to 3s AJAX polling (stored in localStorage). Redis remains purely optional (cache driver only).
8. **CSS is a hand-authored design system** (`assets/css/app.css`, CSS variables, dark mode via `.dark`) rather than a compiled Tailwind artifact — same "no build step, committed static CSS" guarantee, but readable and maintainable. Utility class names intentionally mirror common Tailwind idioms.
9. **Vendored JS** (Alpine, ApexCharts, Drawflow, Sortable, Flatpickr, Choices, SweetAlert2, Wavesurfer, Cropper) live in `assets/vendor/` — no CDN at runtime. Exception: Meta's Facebook JS SDK for Embedded Signup *must* load from Meta (their requirement); it loads on demand only on the connect page.
10. **Composer is optional**: the 4 suggested packages are listed in `composer.json → suggest`; the app never `use`s them unconditionally. CSV import/export, TOTP, QR redirect (via wa.me links) and JWT-free API-key auth all work in pure PHP.

## Domain decisions

11. **Session window**: `conversations.session_expires_at = last_inbound_at + 24h`; every inbound touch extends it. Free-form sends outside the window throw a translated error before any API call; templates always pass.
12. **Message costs**: estimated at send time from the admin-editable `pricing_rates` rate card (longest-prefix country detection), then **overwritten by the authoritative webhook `pricing` object** when Meta delivers it. Reseller markup applies via tenant setting `message_markup_percent`.
13. **Webhook idempotency**: inbound messages dedupe on unique `wamid`; status updates dedupe on unique `(wamid, status)` in `message_status_logs`; status can only progress forward (never `read` → `delivered`).
14. **Campaign engine**: audience snapshot into `campaign_recipients` at launch; a per-minute scheduler tick queues at most `throttle_per_minute` sends per campaign; pause/cancel takes effect mid-flight because the send job re-checks campaign status; completion is detected when no pending+queued remain.
15. **Flow engine**: definition is a JSON node graph (`nodes`, `start`, per-node `next`/`branches`); run state lives in `flow_runs` so delays/waits survive restarts; a 30-step-per-tick budget guards against loops. The flow editor is a validated JSON editor with node type reference and run inspector; the Drawflow visual canvas is vendored and planned as an overlay on the same definition format.
16. **Seeds are ordered by numeric filename prefix** (`01_roles.sql`…) because role permission grants depend on roles existing.
17. **Passwords**: argon2id when the PHP build has it, bcrypt(12) fallback. 2FA is TOTP (pure-PHP RFC 6238) + hashed backup codes; WhatsApp-OTP 2FA is schema-ready (`two_factor_whatsapp`).
18. **Updater trust model**: the GitHub zipball is extracted entry-by-entry with lexical path normalisation, `..`/absolute/drive-letter rejection, and symlink-attribute rejection *before* any file reaches the extraction dir; copies into the live tree are atomic (`.kwctmp` + rename) and never touch protected paths. Rollback restores both the file zip and the pure-PHP SQL dump; a failed rollback keeps maintenance mode on and emails manual recovery steps.
19. **`.htaccess` update rule**: the shipped hash is recorded (`storage/.htaccess_shipped_hash`); if the live file no longer matches the previously shipped version it is treated as admin-modified and protected.
20. **Media**: inbound media downloads run as queue jobs (webhook responds fast); outbound uploads cache `media_id` for 30 days keyed by file path for campaign reuse.
21. **Uploads** are served through an auth-gated controller (`/media/{path}`) that checks the tenant segment of the path — uploads are never directly guessable, and PHP execution is denied in `uploads/` by `.htaccess`.

## Scope notes (see BUILD_PROGRESS.md for the full matrix)

22. AI module, payment-gateway checkout UI, e-commerce store sync drivers, PWA assets and several marketing tools are **schema-complete and partially service-complete** but not yet fully wired to UI. The spec's phases 5–8 breadth exceeds a single build pass; the honest per-feature status lives in BUILD_PROGRESS.md rather than claiming completeness.
