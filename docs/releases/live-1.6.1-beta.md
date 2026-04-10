# Live release 1.6.1 (beta)

**Version:** `1.6.1-beta` (suggested tag `v1.6.1-beta`)  
**Type:** beta (calendar + mobile polish)

## Summary

- **Events / calendar (mobile)** — Day · Week · Month · List segmented control; month/week **grid** shows **title only** (no time) with ellipsis; removed empty-day “No events” placeholders; Events screen wrapper **~99% width** on small viewports.
- **Job detail** — **Select all** checkbox for assigned employees (mass punch).
- **Page headers** — `jt-page-header-actions` keeps Actions + Back on one row with **no awkward text wrap** (md+).

## Database migrations (run on live, in order, once)

Apply only if not already applied:

| File | Purpose |
|------|---------|
| `database/migrations/2026-04-09_live_1.6.1_beta.sql` | Ensures `schema_migrations` exists; records `2026-04-09_live_1.6.1_beta` (no table/column changes). |

## Build live bundle

**Full tree** (includes uncommitted working copy):

```bash
./scripts/build-live-release.sh full junktracker_live_1.6.1_beta
```

**Delta** (after commit + tag), from prior tag (e.g. `v1.6.0`):

```bash
./scripts/build-live-release.sh junktracker_live_1.6.1_beta v1.6.0
```

Output: `junktracker_live_releases/junktracker_live_1.6.1_beta/upload/` and `…/migrations/`.

## Smoke test

- **Events:** mobile segments switch views; month grid shows truncated titles without times; layout uses most of screen width.
- **Jobs:** assigned employees — select all, mass punch in/out.
- **Client / job headers:** Actions + Back align on narrow desktop/tablet.

## Rollback

Redeploy prior upload bundle and previous `config/app.php` version string. DB change is a marker row only; forward-only.
