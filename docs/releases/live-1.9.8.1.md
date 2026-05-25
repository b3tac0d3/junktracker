# Live Release 1.9.8.1

Date: 2026-05-25

Patch on [1.9.8](./live-1.9.8.md).

## Highlights

- **Work in progress:** Shows only upcoming open items — future jobs, open tasks/quotes/deliveries/estate sales/appointments. Past days and closed/completed records are excluded.
- **Completed tasks:** Task detail page makes closed tasks obvious (banner, badges, green styling).

## Changed files

- `app/Models/DashboardSummary.php` — agenda filters for open/upcoming only
- `app/Models/EventFeed.php` — status metadata on calendar events
- `app/Views/home/index.php` — work in progress subtitle
- `app/Views/tasks/show.php` — completed task styling
- `public/assets/css/jt-theme.css` — completed task styles
- `config/app.php` (version `1.9.8.1`)

## Database migrations in this release

None.

## Build live bundle

Delta from 1.9.8:

```bash
./scripts/build-live-release.sh junktracker_live_1.9.8.1 v1.9.8
```

If live has not yet received 1.9.8, deploy [live-1.9.8.md](./live-1.9.8.md) first (or build from `v1.9.6.1` for the full delta).

Output: `junktracker_live_releases/junktracker_live_1.9.8.1/upload/` and `…/migrations/`.

## Ops notes

- **Hard refresh** after deploy.
- **Smoke test:** Dashboard work in progress — no past completed jobs/tasks; future jobs still appear.
- **Smoke test:** Open a closed task — green completed banner and badges visible.

## Related

- Prior release: [live-1.9.8.md](./live-1.9.8.md)
