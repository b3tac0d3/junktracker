# Live Release 1.9.2.2-beta

Date: 2026-05-21

## Highlights

- **Dashboard:** Sales and Estate Sales KPI cards now show correct MTD/YTD totals (fixed key mapping from `Sale::summary()`).
- **Dashboard:** Profit YTD and Total Income net now match Reports logic — general expenses only, no double-counting of job-linked expenses.
- **Dashboard:** Profit YTD uses `ReportSummary` overall net; purchase-adjusted figure remains in parentheses.

## Changed files

- `app/Models/DashboardSummary.php`
- `app/Models/Sale.php`
- `app/Views/home/index.php`
- `config/app.php` (version `1.9.2.2-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.2.2-beta.md`
- `patches/live-1.9.2.2-dashboard-profit.patch` (optional hotfix apply)

## Database migrations in this release

No new database migrations are required for 1.9.2.2-beta.

## Patch-only deploy (FTP / manual upload)

From the project root:

`patch -p1 < patches/live-1.9.2.2-dashboard-profit.patch`

Or copy the files listed above from this bundle’s `upload/` tree.

## Ops notes

- Smoke test: Dashboard — confirm **Sales MTD/YTD** shows non-zero totals when sales exist; **Profit YTD** is positive and consistent with **Reports → Income** overall net; parenthetical value is net after purchase costs.
