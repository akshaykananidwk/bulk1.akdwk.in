# Installation Guide

## 1. Quick install (any host)

1. Upload all files to your web root (aaPanel: `/www/wwwroot/your-domain/`).
2. Create an empty MySQL database (or let the installer create it if your DB user has permission).
3. Open `https://your-domain/install` and follow the wizard:
   - **Requirements** — every ❌ critical check must pass; hints show the aaPanel fix. mod_rewrite is live-tested.
   - **Database** — credentials + optional table prefix; *Test Connection* creates the DB when possible.
   - **Import** — 119 tables + seed data imported with a progress bar; safe to resume.
   - **Admin** — your super-admin account.
   - **Settings** — app name, URL (auto-detected), language (EN/GU/HI), currency.
   - **Cron** — copy the displayed commands (real absolute paths are filled in).
   - **Finish** — writes `config/config.php` + `installed.lock`, locks the installer (renames `install/`).
4. Log in and configure **Admin → Meta App** (App ID, App Secret, Embedded Signup Config ID, webhook verify token).
5. In the Meta App dashboard set the webhook callback URL to `https://your-domain/webhook/meta` with your verify token, subscribed to: `messages`, `message_template_status_update`, `phone_number_quality_update`, `account_update`, `template_category_update`.

## 2. Cron & workers

**Required** (every minute — also drains the queue in cron-only mode):

```
* * * * * /usr/bin/php /www/wwwroot/your-domain/cron/scheduler.php >> /dev/null 2>&1
```

**Recommended** (every 5 minutes — watchdog):

```
*/5 * * * * /usr/bin/php /www/wwwroot/your-domain/cron/watchdog.php >> /dev/null 2>&1
```

**Optional high-throughput workers** (PM2):

```
pm2 start /www/wwwroot/your-domain/cron/worker.php --name kwc-worker --interpreter php -- --queue=default,messages,webhook,ai,media,mail,reports
pm2 start /www/wwwroot/your-domain/cron/campaign.php --name kwc-campaign --interpreter php
pm2 save
```

With PM2 running, set **worker mode = pm2** in Admin → Global Settings so the cron fallback stands down (it still acts as a safety net if workers die).

## 3. aaPanel specifics

- Site → Settings → **URL Rewrite**: Apache reads the shipped `.htaccess` automatically. For Nginx paste:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ ^/(app|config|database|storage|cron|resources|lang|routes)/ { deny all; }
location ~ /\. { deny all; }
```

- PHP 8.3 → install extensions: `pdo_mysql curl mbstring openssl zip gd fileinfo intl bcmath`
- PHP settings: `memory_limit 256M`, `upload_max_filesize 100M`, `post_max_size 100M`
- SSL: use the built-in Let's Encrypt tab — HTTPS is mandatory for Meta webhooks.

## 4. File permissions

```
chown -R www:www /www/wwwroot/your-domain
find /www/wwwroot/your-domain -type d -exec chmod 755 {} \;
find /www/wwwroot/your-domain -type f -exec chmod 644 {} \;
chmod 640 /www/wwwroot/your-domain/config/config.php
```

## 5. Re-running the installer (repair)

The installer locks itself after finishing. To repair, open `/install_disabled_*/` → it shows a locked screen where confirming your **database password** unlocks it. Take a backup first — re-importing can overwrite data.

## 6. Troubleshooting

| Symptom | Fix |
|---|---|
| Redirect loop to `/install` | `installed.lock` or `config/config.php` missing/unreadable |
| 500 after install | Check `storage/logs/error-*.log`; verify PHP extensions |
| Messages stuck in "queued" | Cron not running — test with the installer's cron check or Admin → System Health |
| Webhook not receiving | HTTPS + verify token + App Secret must match; see `storage/logs/webhook-*.log` |
| SSE not updating inbox | Proxy buffering — the client auto-falls back to polling; on Nginx add `proxy_buffering off;` for `/sse/` |
