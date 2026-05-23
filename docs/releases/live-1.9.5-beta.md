# Live Release 1.9.5-beta

Date: 2026-05-21

## Highlights

- **Estate sale metrics (admin):** New **Metrics** tab on each estate sale — split breakdown by client %, customer counts, average wait/shopping time, average sale price, per-day stats for the sale date range, profit-after-labor calculations, and charts.
- **Field ops on sales:** Payment type on transactions (cash, Venmo, Cash App, PayPal, credit card, check, other); sale date **and time**; per-sale client split % override (bold when different from estate default); recording audit (who/when) on sale detail.
- **Duplicate-save guard:** Customer quick-add modal and Add Sale form lock the save button after click, show a spinner/progress bar, and block double submits on slow cell service.
- **Smarter navigation:** Opening a sale from an estate sale returns to that sale (Sales tab or customer page) instead of the global records list.
- **Customers:** Customer detail page with check-in/out, add sale, edit, and remove (admin); Actions dropdown on customer rows; remembered pagination on estate sale tabs.
- **Jobs:** Remove assigned employees from a job when they have no open punch on that job.

## Changed files

- `app/Models/EstateSale.php` — metrics report, client split enrichment, labor helpers
- `app/Controllers/EstateSalesController.php` — metrics data, customer CRUD/check-in, sale create/update
- `app/Models/Sale.php`, `app/Controllers/SalesController.php` — payment method, client %, return navigation
- `app/Views/estate-sales/show.php`, `metrics_tab.php`, `sale_form.php`, `customer_show.php`, `customer_form.php`, `records.php`
- `app/Views/sales/show.php`, `app/helpers.php` — back URLs, submit helpers
- `app/Controllers/JobsController.php`, `app/Models/Job.php`, `app/Models/TimeEntry.php`, `app/Views/jobs/show.php`
- `public/assets/js/app.js`, `public/assets/css/jt-theme.css` — submit lock UI, tab colors
- `routes/web.php`
- `config/app.php` (version `1.9.5-beta`)

## Database migrations in this release

Run in filename order (idempotent):

1. `database/migrations/2026-05-21_sales_client_percentage.sql` — optional per-sale client split override
2. `database/migrations/2026-05-21_sales_payment_method.sql` — payment type on sales (default `cash`)

## Ops notes

- **Admin/financial:** Metrics, expenses, and sale financial detail require workspace admin (or site admin).
- **Smoke test:** Add a customer on an estate sale with slow network simulation — save button should lock and show progress; only one record created.
- **Smoke test:** Add a sale with payment type and time; confirm Metrics tab shows per-day breakdown for the sale’s start/end dates.
- **Smoke test:** Open a sale from an estate sale Sales tab — Back should return to that estate sale, not Estate Sale Records.
