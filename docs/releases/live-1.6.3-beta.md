# Live release 1.6.3 (beta)

**Version:** `1.6.3-beta` (suggested tag `v1.6.3-beta`)  
**Type:** beta (billing estimates, filters, sales/purchases UI)

## Summary

- **Billing** — Terminal estimates hidden from list (`declined`, `closed`, `converted`, `cancelled`); estimate totals **blue**; **converted** status + migration; convert-to-invoice marks source estimate.
- **Sales & purchases** — **Filters** match **Billing** layout (row 1: Search, Type/Status, Sort By; row 2: From, To, Sort Order, Apply/Clear).
- **Earlier 1.6.x** — See prior release notes for dashboard profit detail, client Add Sale, etc.

## Database migrations (run on live, in order, once)

Apply only if not already applied:

| File | Purpose |
|------|---------|
| `database/migrations/2026-04-11_estimate_status_converted.sql` | Adds `converted` to `invoices.status` ENUM (required for converted estimates). |
| `database/migrations/2026-04-12_live_1.6.3_beta.sql` | Marker `2026-04-12_live_1.6.3_beta` (no table/column changes). |

## Build live bundle (delta — changed files only)

After committing, from the **tag or commit last deployed** (example `v1.5.3` if that was live):

```bash
./scripts/build-live-release.sh junktracker_live_1.6.3_beta v1.5.3
```

If you already tagged **v1.6.2-beta** on the commit that went to production, use that as the base instead for a smaller patch:

```bash
./scripts/build-live-release.sh junktracker_live_1.6.3_beta v1.6.2-beta
```

**Do not** use `full` unless you need a complete tree. Output: `junktracker_live_releases/junktracker_live_1.6.3_beta/upload/` and `…/migrations/`.

## Smoke test

- Billing list: open estimates show blue totals; converted/declined estimates not listed.
- Convert estimate → invoice: source estimate gets **converted** (after ENUM migration).
- Sales & purchases: filter layout matches billing; date range and sort work.

## Rollback

Redeploy prior upload bundle and previous `config/app.php` version string. Run DB rollbacks only if you applied new SQL; marker rows are forward-only.
