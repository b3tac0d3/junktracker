# Live release 1.6.0

**Version:** `1.6.0` (tag `v1.6.0`)  
**Type:** minor (UX + punch workflow)

## Summary

- **Punch board — switch job while clocked in** — Ends the current open punch and starts a new one at the same timestamp (no gap). `POST /time-tracking/switch-job`.
- **Mobile page headers** — Detail and list headers use stacked toolbars or **Actions** dropdowns so buttons are not cramped on small screens.
- **Flash** — `flash('info')` supported in the main layout (Bootstrap `alert-info`).

## Database migrations (run on live, in order, once)

Apply only if not already applied:

| File | Purpose |
|------|---------|
| `database/migrations/2026-03-27_live_1.6.0.sql` | Ensures `schema_migrations` exists; records `2026-03-27_live_1.6.0` (no table/column changes). |

## Build live bundle

From repo root, after committing and tagging `v1.6.0`:

```bash
./scripts/build-live-release.sh junktracker_live_1.6.0 v1.5.3
```

Adjust the base ref (`v1.5.3`) to whatever was last deployed on the server. Output: `junktracker_live_releases/junktracker_live_1.6.0/upload/` and `…/migrations/`.

## Smoke test

- Punch board: punch in, use **Switch** to another job or non-job type; confirm two segments back-to-back with the same boundary time.
- Time entry detail / job-style pages: header actions usable on a narrow viewport.
- Optional: trigger an info flash (e.g. switch to same job) and confirm blue alert.

## Rollback

Redeploy prior upload bundle and previous `config/app.php` version string. DB change is a marker row only; forward-only.
