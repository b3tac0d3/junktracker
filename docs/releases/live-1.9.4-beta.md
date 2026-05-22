# Live Release 1.9.4-beta

Date: 2026-05-22

## Highlights

- **Role-based financial access:** General users can no longer view reports, billing, expenses, purchases, deposits, or general sales modules.
- **Dashboard:** KPI cards, income chart, and financial list panels hidden for general users.
- **Navigation:** Finance and Reports hidden; general users see Estate Sales only under Sales (not Purchases or Estate Sale Records).
- **Detail pages:** Financial summaries hidden on clients, jobs, and estate sales; dollar amounts masked where operational access remains (e.g. on-site sale entry).

## Changed files

- `app/helpers.php` (`can_view_financials`, `require_financial_access`)
- `app/Controllers/BillingController.php`, `DepositsController.php`, `ExpensesController.php`, `PurchasesController.php`, `ReportsController.php`, `SalesController.php`, `SearchController.php`, `EstateSalesController.php`
- `app/Models/NavNotifications.php`
- `app/Views/layouts/main.php`, `home/index.php`, `clients/show.php`, `jobs/show.php`, `estate-sales/index.php`, `estate-sales/show.php`, `estate-sales/customer_show.php`
- `config/app.php` (version `1.9.4-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.4-beta.md`

## Database migrations in this release

No new database migrations are required for 1.9.4-beta.

## Ops notes

- Smoke test as **General User:** no Reports/Finance nav; dashboard has no KPI row; direct URL to `/reports` returns 403.
- Smoke test as **Admin:** full financial access unchanged.
