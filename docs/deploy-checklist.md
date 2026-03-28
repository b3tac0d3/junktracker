# Production deploy checklist

Short list to keep releases predictable. Pair with delta bundles from `scripts/build-live-release.sh` and version tags.

**“Run live”** (release phrase): bump `config/app.php` to the new version, commit and push, tag `vX.Y.Z` on that commit, then build the upload folder with `./scripts/build-live-release.sh junktracker_beta_X.Y.Z v<previous_tag>` (delta from the prior tag). Example: `./scripts/build-live-release.sh junktracker_beta_1.3.9 v1.3.8`.

1. **Database** — Run new SQL in `database/migrations/` in filename order for this release only (do not re-apply old migrations).
2. **Config** — Merge `config/app.php`, `config/database.php`, `config/mail.php` on the server; set `app.url`, `app.env`, and optionally `JUNKTRACKER_CRON_KEY` for cron.
3. **Permissions** — Ensure the web server can write `storage/logs`, `storage/` if used, and `public/` uploads (e.g. business logos under `public/uploads/` or paths your app uses).
4. **Cron (optional)** — Daily digest: `GET /cron/daily-digest?key=YOUR_CRON_KEY` (same value as `app.cron_key` / `JUNKTRACKER_CRON_KEY`).
5. **Smoke test** — Login, open one job, one billing record, run reports for current month.
