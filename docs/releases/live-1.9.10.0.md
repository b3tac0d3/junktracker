# Live Release 1.9.10.0

Date: 2026-05-25

Release after [1.9.8.2](./live-1.9.8.2.md). Billing and receivables workflow improvements for day-to-day use.

## Highlights

- **Dashboard past due:** Past-due open invoices appear in the dashboard Past due section (grouped by due date, admin-only).
- **Jobs receivables:** Past due invoice total includes completed jobs; clickable link opens Billing → Invoices → Past due section.
- **Billing — Invoices tab:** Records grouped into **Past due**, **Unpaid**, and **Paid** with summary totals; open balances shown for unpaid buckets.
- **Billing — Estimates tab:** Separate flat list for estimates (no payment categories).
- **Invoice status:** New **Non Payment / Write Off** status (`write_off`); excluded from open/past-due receivables like paid invoices.

## Changed files

- `app/Models/Invoice.php` — grouped billing queries, payment buckets, estimates list, write-off handling
- `app/Models/DashboardSummary.php` — past-due invoice agenda items
- `app/Models/Job.php` — business-wide past-due invoice total for jobs summary
- `app/Controllers/BillingController.php` — invoices/estimates tabs
- `app/Models/FormSelectValue.php` — default invoice status `write_off`
- `app/Views/billing/index.php` — tabbed UI with invoice categories
- `app/Views/billing/form.php`, `app/Views/home/index.php`, `app/Views/jobs/index.php`, `app/Views/jobs/show.php`
- `app/Models/NavNotifications.php`, `app/Models/Digest.php`, `app/Controllers/AdminExportController.php`
- `public/assets/css/jt-theme.css` — billing bucket styling
- `config/app.php` (version `1.9.10.0`)

## Database migrations in this release

- `database/migrations/2026-05-25_invoice_status_write_off.sql` — adds `write_off` to `invoices.status` ENUM and seeds form select value

Run migration on live **before** or **with** file deploy.

## Build live bundle

Delta from 1.9.8.2:

```bash
./scripts/build-live-release.sh junktracker_live_1.9.10.0 v1.9.8.2
```

Output: `junktracker_live_releases/junktracker_live_1.9.10.0/upload/` and `…/migrations/`.

## Ops notes

- **Hard refresh** after deploy.
- **Run migration** for write-off status.
- **Smoke test:** Dashboard Past due — overdue invoices appear for admins.
- **Smoke test:** Jobs — Past Due Invoices total includes completed-job invoices; link opens Billing Invoices tab at Past due.
- **Smoke test:** Billing — Invoices tab shows three categories; Estimates tab shows flat list.
- **Smoke test:** Mark an invoice **Non Payment / Write Off** — drops out of past due/unpaid totals.

## Related

- Prior release: [live-1.9.8.2.md](./live-1.9.8.2.md)
- Next up: Google Calendar one-way sync (outbound appointments)
