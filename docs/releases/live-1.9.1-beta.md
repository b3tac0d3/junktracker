# Live Release 1.9.1-beta

Date: 2026-05-21

## Highlights

- **Sales list:** Estate on-site transactions no longer appear on the general **Sales** list — keeps that view focused on larger/shop/job sales.
- **Estate Sale Records:** New page at `/estate-sale-records` — same layout as Sales (summary, filters, searchable list) but only estate-sale-linked transactions, with estate sale name and customer columns.
- **Navigation:** **Sales → Estate Sale Records** in the sidebar.
- **Metrics:** Dashboard, reports, and estate sale financials still include all sales totals (unchanged).

## Changed files

- `app/Models/Sale.php`
- `app/Controllers/EstateSalesController.php`
- `app/Controllers/SalesController.php`
- `app/Controllers/SearchController.php`
- `app/Views/estate-sales/records.php` (new)
- `app/Views/sales/index.php`, `app/Views/sales/show.php`
- `app/Views/layouts/main.php`
- `routes/web.php`
- `config/app.php` (version `1.9.1-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.1-beta.md`
- `patches/live-1.9.1-estate-sale-records.patch` (optional hotfix apply)

## Database migrations in this release

No new database migrations are required for 1.9.1-beta.

## Patch-only deploy (FTP / manual upload)

From the project root:

`patch -p1 < patches/live-1.9.1-estate-sale-records.patch`

Or copy the files listed above from this bundle’s `upload/` tree.

## Ops notes

- Smoke test: **Sales** — confirm estate on-site items are gone; **Sales → Estate Sale Records** — confirm they appear with search/sort; open one record and verify **Back to Estate Sale Records**.
- Dashboard/reports totals should match pre-release (all sales still counted in metrics).
