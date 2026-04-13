# Live release 1.6.4 (beta)

**Version:** `1.6.4-beta` (suggested tag `v1.6.4-beta`)  
**Type:** beta (quote visibility on calendar + dashboard)

## Summary

- **Jobs / Forms** — `quote` is now included as a default `job_type` option.
- **Events calendar** — quote jobs have a distinct **purple** color to separate quote work from regular jobs.
- **Event preview + calendar labels** — customer names display in the quick-view and inline event titles where available.
- **Dashboard** — new **Outstanding Quotes** panel powered by billing estimates (non-terminal statuses).

## Database migrations (run on live, in order, once)

Apply only if not already applied:

| File | Purpose |
|------|---------|
| `database/migrations/2026-04-13_live_1.6.4_beta.sql` | Marker `2026-04-13_live_1.6.4_beta` (no table/column changes). |

## Build live bundle (delta — changed files only)

After committing and tagging this release:

```bash
./scripts/build-live-release.sh junktracker_live_1.6.4_beta v1.5.3
```

**Do not** use `full` unless you need a complete tree. Output: `junktracker_live_releases/junktracker_live_1.6.4_beta/upload/` and `…/migrations/`.

## Smoke test

- Create/edit a job and confirm **Quote** is available in Job Type.
- Calendar: quote jobs render purple; non-quote jobs keep existing colors.
- Event quick-view and list/month/week/day entries show customer names when present.
- Dashboard: **Outstanding Quotes** lists open estimates and links to billing records.

## Rollback

Redeploy prior upload bundle and previous `config/app.php` version string. DB marker row is forward-only.
