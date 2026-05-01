# Live Release 1.8.2.3-beta

Date: 2026-05-01

## Highlights

- Dashboard service KPIs now use payments received (cash basis) instead of invoice totals.
- Added a new dashboard KPI for `Payments Due` with open invoice count.
- Job deactivation flow now returns users to the jobs index after deactivation.

## Database migrations in this release

No new database migrations are required for 1.8.2.3-beta.

## Ops notes

- Create an invoice/deposit/payment and confirm dashboard service totals reflect payments received.
- Confirm `Payments Due` changes as payments are applied.
- Deactivate a job and confirm redirect lands on `/jobs`.
