# Live Release 1.9.5.1-beta

Date: 2026-05-23

## Highlights

- **Metrics — employee labor:** Per-employee hours, rate, and amount owed for each sale day, plus grand totals across all days.
- **Index NET fix:** Estate sales list now loads full split settings so NET matches the detail page and metrics (was under-counting client share when only per-sale overrides existed).

## Changed files

- `app/Models/EstateSale.php` — `metricsLaborBreakdown()`, index/financial summary split loading fix
- `app/Views/estate-sales/metrics_tab.php` — employee labor tables (overall, daily summary, per-day)
- `config/app.php` (version `1.9.5.1-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.5.1-beta.md`

## Database migrations in this release

No new database migrations are required for 1.9.5.1-beta.

## Ops notes

- Smoke test: open an estate sale **Metrics** tab — confirm employee labor totals match the **Labor** tab time logs.
- Smoke test: estate sales index NET should match the sale detail financial summary for sales with mixed client split overrides.
