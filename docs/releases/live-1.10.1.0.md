# Live Release 1.10.1.0

Date: 2026-05-25

Release after [1.9.10.0](./live-1.9.10.0.md). Billing UX polish and dashboard past-due invoice date accuracy.

## Highlights

- **Billing date filter:** Defaults to year-to-date (Jan 1 through today) on load and clear.
- **Billing pagination:** Invoices tab paginates past due → unpaid → paid while keeping full bucket totals; Estimates tab uses standard SQL pagination.
- **Dashboard past due:** Invoice rows group by **due date** (not issue date); each row shows the due date in the time column.
- **Dashboard:** Removed the Past due section subtitle.

## Changed files

- `app/Controllers/BillingController.php` — YTD date defaults, pagination wiring
- `app/Models/Invoice.php` — billing record count, limit/offset, grouped pagination
- `app/Views/billing/index.php` — pagination controls
- `app/Models/DashboardSummary.php` — due-date grouping for past-due invoice agenda items
- `app/Views/home/index.php` — Past due header only
- `config/app.php` (version `1.10.1.0`)
- `scripts/verify-dashboard-kpis.php` — optional offline KPI audit against SQL dumps

## Database migrations in this release

None.

## Build live bundle

Delta from 1.9.10.0:

```bash
./scripts/build-live-release.sh junktracker_live_1.10.1.0 v1.9.10.0
```

Output: `junktracker_live_releases/junktracker_live_1.10.1.0/upload/`.

## Ops notes

- **Hard refresh** after deploy.
- **Smoke test:** Billing loads with YTD dates pre-filled; pagination works on Invoices and Estimates tabs.
- **Smoke test:** Dashboard Past due — invoice rows show due date and group under the correct day.

## Related

- Prior release: [live-1.9.10.0.md](./live-1.9.10.0.md)
