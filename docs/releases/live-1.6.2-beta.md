# Live release 1.6.2 (beta)

**Version:** `1.6.2-beta` (suggested tag `v1.6.2-beta`)  
**Type:** beta (billing + dashboard + client actions)

## Summary

- **Billing index** — Cards show **Invoice # / Estimate #** with color-coded total (paid / partial / unpaid); second line **client · job · date**; **date range** filters; two-row filter layout.
- **Dashboard** — **Profit YTD** shows **after purchase costs (YTD)** in parentheses (same logic as Reports → Income → “After purchases”).
- **Client detail** — **Actions** menu includes **Add Sale** (prefills client on the sale form).

## Database migrations (run on live, in order, once)

Apply only if not already applied:

| File | Purpose |
|------|---------|
| `database/migrations/2026-04-10_live_1.6.2_beta.sql` | Ensures `schema_migrations` exists; records `2026-04-10_live_1.6.2_beta` (no table/column changes). |

## Build live bundle

**Full tree** (includes uncommitted working copy):

```bash
./scripts/build-live-release.sh full junktracker_live_1.6.2_beta
```

**Delta** (after commit + tag), from prior tag (e.g. `v1.6.1-beta`):

```bash
./scripts/build-live-release.sh junktracker_live_1.6.2_beta v1.6.1-beta
```

Output: `junktracker_live_releases/junktracker_live_1.6.2_beta/upload/` and `…/migrations/`.

## Smoke test

- **Billing:** filters (search, status, sort, date range); list rows match new layout; totals colored by status.
- **Dashboard:** Profit YTD main value and parenthetical; link to Reports still works.
- **Client:** Actions → Add Sale opens form with client filled.

## Rollback

Redeploy prior upload bundle and previous `config/app.php` version string. DB change is a marker row only; forward-only.
