# Production deploy checklist

Short list to keep releases predictable. Pair with delta bundles from `scripts/build-live-release.sh` and version tags.

**Latest release notes:** [releases/live-1.9.6.0.md](./releases/live-1.9.6.0.md) (estate customer reuse, jobs billing summary, calendar day nav).  
**Earlier:** [releases/live-1.9.5.1-beta.md](./releases/live-1.9.5.1-beta.md), [releases/live-1.9.5-beta.md](./releases/live-1.9.5-beta.md), [releases/live-1.9.4.3-beta.md](./releases/live-1.9.4.3-beta.md), [releases/live-1.9.4.2-beta.md](./releases/live-1.9.4.2-beta.md), [releases/live-1.9.4.1-beta.md](./releases/live-1.9.4.1-beta.md), [releases/live-1.9.4-beta.md](./releases/live-1.9.4-beta.md), [releases/live-1.9.3.1-beta.md](./releases/live-1.9.3.1-beta.md), [releases/live-1.9.3.0-beta.md](./releases/live-1.9.3.0-beta.md), [releases/live-1.9.2.4-beta.md](./releases/live-1.9.2.4-beta.md), [releases/live-1.9.2.3-beta.md](./releases/live-1.9.2.3-beta.md), [releases/live-1.9.2.2-beta.md](./releases/live-1.9.2.2-beta.md), [releases/live-1.9.2.1-beta.md](./releases/live-1.9.2.1-beta.md), [releases/live-1.9.2-beta.md](./releases/live-1.9.2-beta.md), [releases/live-1.9.1-beta.md](./releases/live-1.9.1-beta.md), [releases/live-1.9.0.1-beta.md](./releases/live-1.9.0.1-beta.md), [releases/live-1.9.0-beta.md](./releases/live-1.9.0-beta.md), [releases/live-1.8.3.3-beta.md](./releases/live-1.8.3.3-beta.md), [releases/live-1.8.3.2-beta.md](./releases/live-1.8.3.2-beta.md), [releases/live-1.8.3.1-beta.md](./releases/live-1.8.3.1-beta.md), [releases/live-1.8.3-beta.md](./releases/live-1.8.3-beta.md), [releases/live-1.8.2.3-beta.md](./releases/live-1.8.2.3-beta.md), [releases/live-1.8.2.2-beta.md](./releases/live-1.8.2.2-beta.md), [releases/live-1.8.2.1-beta.md](./releases/live-1.8.2.1-beta.md), [releases/live-1.8.2-beta.md](./releases/live-1.8.2-beta.md), [releases/live-1.8.1-beta.md](./releases/live-1.8.1-beta.md), [releases/live-1.8.0-beta.md](./releases/live-1.8.0-beta.md), [releases/live-1.7.2.md](./releases/live-1.7.2.md), [releases/live-1.7.0.md](./releases/live-1.7.0.md), [releases/live-1.6.4-beta.md](./releases/live-1.6.4-beta.md), [releases/live-1.6.3-beta.md](./releases/live-1.6.3-beta.md), [releases/live-1.6.2-beta.md](./releases/live-1.6.2-beta.md), [releases/live-1.6.1-beta.md](./releases/live-1.6.1-beta.md), [releases/live-1.6.0.md](./releases/live-1.6.0.md), [releases/live-1.5.3.md](./releases/live-1.5.3.md).

### Beta-live naming convention (standard)

- **App version (`config/app.php`)**: `<major>.<minor>.<patch>-beta` or `<major>.<minor>.<patch>.<hotfix>-beta` (examples: `1.8.2-beta`, `1.8.2.1-beta`)
- **Release notes file**: `docs/releases/live-<version>.md`
- **Bundle folder name**: `junktracker_beta_<version>`
- **Commit title**: `Release <version>: <short summary>`

**“Run live”** (release phrase): bump `config/app.php` to the new version, commit and push, tag `vX.Y.Z` on that commit, then build with `./scripts/build-live-release.sh junktracker_beta_X.Y.Z v<previous_tag>` (delta from the prior tag). Example: `./scripts/build-live-release.sh junktracker_beta_1.5.0 v1.4.0.3`.

**One-command beta prep:** `./scripts/new-beta-release.sh <major>.<minor>.<patch>-beta "short summary"`  
Example: `./scripts/new-beta-release.sh 1.8.3-beta "quote reminders and calendar polish"`

The script writes two trees next to each other: **`…/<name>/upload/`** (what goes on the web server) and **`…/<name>/migrations/`** (SQL only). Database migrations are **never** placed inside the upload folder.

1. **Database** — Run new SQL from the release’s **`migrations/`** folder (or from `database/migrations/` in the repo) in filename order for this release only (do not re-apply old migrations).
2. **Config** — Merge `config/app.php`, `config/database.php`, `config/mail.php` on the server. Non-localhost hostnames auto-use `production` env and real mail; localhost stays dev/log-only. Optional overrides: `config/app.local.php` (e.g. `url`, `cron_key`), `config/mail.local.php`, `JUNKTRACKER_CRON_KEY` for cron.
3. **Permissions** — Ensure the web server can write `storage/logs`, `storage/` if used, and `public/` uploads (e.g. business logos under `public/uploads/` or paths your app uses).
4. **Cron (optional)** — Daily digest: `GET /cron/daily-digest?key=YOUR_CRON_KEY` (same value as `app.cron_key` / `JUNKTRACKER_CRON_KEY`).
5. **Smoke test** — Login, open one job, one billing record, run reports for current month.

### Sessions / “Remember me” (logged out after ~15 minutes)

PHP’s default `session.gc_maxlifetime` is often **900** seconds. The app raises it via `ini_set` and uses `storage/sessions/` when writable. If the host **blocks** `ini_set` for sessions, set **`session.gc_maxlifetime`** in **php.ini**, **`public/.user.ini`**, or the host panel (use a large value, e.g. `31536000` for one year). Ensure **`storage/sessions`** exists and is writable by the web server (the app creates it on first request).
