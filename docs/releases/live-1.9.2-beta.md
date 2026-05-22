# Live Release 1.9.2-beta

Date: 2026-05-21

## Highlights

- **Dashboard:** New **Estate Sales MTD / YTD** KPI card with gross/net, separate from general Sales.
- **Dashboard:** **Total Income** and 3-month chart now include estate sales (new purple **Estate sales gross** bar).
- **Dashboard:** New **Recent Estate Sale Records (MTD)** panel.
- **Income report:** New **Estate Sales** section (count, gross, net); general **Sales** section excludes on-site estate records.
- **CSV export:** Income report CSV includes estate sales summary.

## Changed files

- `app/Models/Sale.php`
- `app/Models/DashboardSummary.php`
- `app/Models/ReportSummary.php`
- `app/Controllers/ReportsController.php`
- `app/Views/home/index.php`
- `app/Views/reports/income.php`
- `public/assets/css/jt-theme.css`
- `config/app.php` (version `1.9.2-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.2-beta.md`
- `patches/live-1.9.2-estate-sales-metrics.patch` (optional hotfix apply)

## Database migrations in this release

No new database migrations are required for 1.9.2-beta.

## Patch-only deploy (FTP / manual upload)

From the project root:

`patch -p1 < patches/live-1.9.2-estate-sales-metrics.patch`

Or copy the files listed above from this bundle’s `upload/` tree.

## Ops notes

- Smoke test: Dashboard — confirm **Estate Sales** KPI and recent-records panel; **Reports → Income** — confirm **Estate Sales** section and that overall gross includes estate + sales + service.
