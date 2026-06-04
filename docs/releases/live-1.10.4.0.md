# Live Release 1.10.4.0

Date: 2026-06-04

Patch on [1.10.3.0](./live-1.10.3.0.md).

## Highlights

- **Google Calendar:** Past backfill (365 days) and upcoming (90 days) buttons in Settings; Gmail notifications on appointment create/update/cancel/delete.
- **Jobs:** Labor tab (employee totals, punch clock), disposal weight on disposal expenses, bonus payouts as labor expenses.
- **Time tracking:** Employee time cards views.
- **Sales / invoices:** Restore soft-deleted invoices; related form fixes.
- **Dev tracker:** Site-admin bugs/updates/notes at `/dev`.
- **Events:** Mobile calendar CSS polish; purchase quotes 500 fix.

## Database migrations in this release

Run in order on live (also staged under `migrations/` next to `upload/`):

1. `2026-06-05_expense_disposal_weight.sql`
2. `2026-06-05_expense_employee_bonus.sql`
3. `2026-06-06_gmail_appointment_notify.sql`
4. `2026-06-06_dev_tracker.sql`

## Build live bundle

Delta from 1.10.3.0:

```bash
./scripts/build-live-release.sh live v1.10.3.0
```

Output: `junktracker_live_releases/live/upload/` (replaces any prior live drop).

## Ops notes

- **Google:** Ensure `config/google.local.php` on server; OAuth redirect `https://junktracker.jimmysjunk.com/settings/google-calendar/callback`; Calendar + `gmail.send` scopes.
- **Hard refresh** after deploy (version cache bust).
- **Smoke test:** Settings → Sync past (365 days) → Sync upcoming (90 days); create appointment → appears on Google Calendar.

## Related

- Prior release: [live-1.10.3.0.md](./live-1.10.3.0.md)
