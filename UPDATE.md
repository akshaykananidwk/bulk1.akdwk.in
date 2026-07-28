# GitHub Auto-Update System

Once configured, you never upload files by FTP again.

## One-time setup (Admin → System → Updates)

| Field | Notes |
|---|---|
| Owner / Repository / Branch | Your deployment repo (private or public) |
| GitHub token | Fine-grained PAT with **Contents: Read** on that repo. Stored AES-256-GCM encrypted, shown masked, never logged. |
| Channel | `branch` (every commit) or `release_tag` |
| Auto-check | Daily check → banner + email when an update exists |
| Auto-install | Off by default; when on, installs during the 03:00 window |
| Keep backups | How many pre-update backup pairs to retain (default 5) |

Click **Test Connection** to verify (shows repo name, visibility, default branch, last push).

First-time note: if the installed commit SHA is unknown, paste it once in the settings ("Installed commit SHA") so *Check for Update* can diff correctly.

## Check for Update

Calls the GitHub API (`commits/{branch}`, `compare/{sha}...{branch}`, `contents/version.json`) and shows: new version, commit + author + date, commits behind, changed files (expandable), full changelog, estimated size, and a ⚠️ breaking-change warning when `version.json` sets `"breaking": true`. Results cache for 15 minutes.

## Update Now — the 11 steps

Requires super-admin + `update.manage` + your password re-entered. Progress streams live:

```
[1/11] Preflight checks          — lock, zip ext, disk ≥3× package, PHP/min ext, DB healthy
[2/11] Maintenance mode          — 503 page for everyone except your IP; workers pause
[3/11] File backup               — zip of the app (uploads/backups/tmp/cache/.git excluded)
[4/11] Database backup           — pure-PHP mysqldump → .sql.gz (500-row chunks, no exec())
[5/11] Download from GitHub      — zipball streamed to disk, resume + retries
[6/11] Verify & extract          — zip-slip/symlink/absolute-path entries HARD-REJECTED;
                                   must contain version.json; wrapper folder auto-detected
[7/11] Copy files                — protected paths skipped, atomic per-file writes,
                                   identical files skipped (SHA-1), update.json delete[] honoured
[8/11] Migrations                — new database/migrations/*.sql, recorded with timing
[9/11] Clear cache               — file cache, OPcache reset, assets_version bump
[10/11] Finalize                 — version/SHA bump, update_history row, prune old backups, email
[11/11] Maintenance off
```

### Protected paths (never touched)

`config/config.php`, `.env`, `installed.lock`, `uploads/`, `storage/`, `assets/custom/`, plus anything in `update.json → protect[]`. A hand-modified `.htaccess` is preserved; an unmodified one updates normally.

## Automatic rollback

Any failure in steps 6–10 triggers:

```
[R1] Restore files from the backup zip
[R2] Restore database from the SQL dump
[R3] Clear cache
[R4] Record the failure (update_history, status=rolled_back)
[R5] Maintenance off
[R6] Email the full (redacted) error to the admin
```

If rollback itself fails, maintenance mode **stays on** and the exact manual recovery steps (backup paths included) are shown on screen and emailed.

## Manual rollback

Update history keeps every attempt with its backup pair — click **Restore** on any retained entry (password confirmation required).

## Integrity checker

**Verify Installation** compares local file hashes against the GitHub tree at the installed commit and lists missing / modified / extra files — useful after a failed update or suspected tampering.

## Shipping an update (for maintainers)

1. Bump `version.json` (`version`, `build`, `changelog[]`, `min_php`, `breaking`).
2. Update `update.json` if you need `protect[]`/`delete[]`/`post_update_sql[]`.
3. Add any schema change as `database/migrations/NNNN_name.sql` (+ optional `rollback/NNNN_name.sql`).
4. Commit & push to the deployment branch. Installations see it on their next check.
