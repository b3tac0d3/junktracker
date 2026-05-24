# Live Release 1.9.6.0

Date: 2026-05-24

## Highlights

- **Estate customers (People):** New **Estate Customers** index under People — search all customers across sales, duplicate detection, subscriber preferences (future sales + contact method).
- **Reuse customers across sales:** Find existing customers when adding to a sale (name or phone search); attach without duplicating profiles. Requires memberships migration.
- **Jobs list billing:** **Price** column (invoice if present, else estimate) with color coding — blue estimate, green invoice, red past due. Summary bar shows pending/past-due invoices, pending estimates, and total invoice due (filtered jobs).
- **Calendar:** Click a day in month view to open that day in day view.
- **Billing detail:** Client and Job fields link to their detail pages when IDs exist.

## Changed files

- `app/Controllers/EstateSaleCustomersController.php` — estate customers index, duplicate check API
- `app/Controllers/EstateSalesController.php` — customer profile search, attach customer, quick-create duplicate flow
- `app/Models/EstateSale.php` — memberships, cross-sale attach/search, subscriber fields, duplicate matching
- `app/Models/Job.php` — index billing enrichment, filtered billing summary
- `app/Views/estate-customers/index.php` — global estate customer list
- `app/Views/estate-sales/show.php` — find/add customer modal, attach flow, subscriber fields
- `app/Views/estate-sales/customer_form.php`, `customer_show.php` — subscriber fields, duplicate check
- `app/Views/jobs/index.php` — price column and billing summary
- `app/Views/billing/show.php` — client/job links
- `app/Views/events/index.php` — calendar day navigation
- `app/Views/layouts/main.php`, `app/helpers.php`, `routes/web.php` — nav and routes
- `public/assets/css/jt-theme.css`, `jt-calendar.css` — billing price colors
- `config/app.php` (version `1.9.6.0`)

## Database migrations in this release

Run in filename order (idempotent):

1. `database/migrations/2026-05-24_estate_sale_customers_subscriber.sql` — `subscribes_to_future_sales`, `future_sales_contact_method` on customers
2. `database/migrations/2026-05-24_estate_sale_customer_memberships.sql` — per-sale membership table + backfill from existing customers (**required** for “Add to sale” from search)

## Ops notes

- **Migrations first:** Run both SQL files above before uploading code. Attach-from-search fails with 422 if memberships table is missing.
- **Smoke test:** Estate sale → Customers → Add customer → Find existing → search by name/phone → Add to sale.
- **Smoke test:** Jobs list — confirm Price colors and summary totals for pending invoices, past due, estimates.
- **Smoke test:** Calendar month view — click a date → lands on day view for that date.
- **Smoke test:** Billing detail — Client and Job links open correct records.
