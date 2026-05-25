# Live Release 1.9.8.2

Date: 2026-05-21

Patch on [1.9.8.1](./live-1.9.8.1.md).

## Highlights

- **Past due:** New dashboard section above Work in progress — same calendar-day agenda format for open jobs, quotes, deliveries, purchase quotes, estate sales, tasks, and appointments whose scheduled/due time has passed (including jobs not marked complete or cancelled).
- **Work in progress:** Overdue tasks today no longer duplicate in both sections; they appear only under Past due.

## Changed files

- `app/Models/DashboardSummary.php` — `pastDueSchedule()`, shared agenda builder, past-due filters
- `app/Views/home/index.php` — Past due section above Work in progress
- `public/assets/css/jt-theme.css` — past-due card styling
- `config/app.php` (version `1.9.8.2`)

## Database migrations in this release

None.

## Build live bundle

Delta from 1.9.8.1:

```bash
./scripts/build-live-release.sh junktracker_live_1.9.8.2 v1.9.8.1
```

Output: `junktracker_live_releases/junktracker_live_1.9.8.2/upload/` and `…/migrations/`.

## Ops notes

- **Hard refresh** after deploy.
- **Smoke test:** Dashboard Past due — overdue open jobs and tasks appear; completed/cancelled items do not.
- **Smoke test:** Work in progress — still shows upcoming open items only; no overlap with Past due for overdue tasks today.

## Related

- Prior release: [live-1.9.8.1.md](./live-1.9.8.1.md)
