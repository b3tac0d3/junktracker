# Live Release 1.10.2.1

Date: 2026-05-27

Patch on [1.10.2](./live-1.10.2.md).

## Highlights

- **Quote dates:** Show created, follow-up, and status dates (won/lost/sent/etc.) on the client Jobs tab quote list, main Quotes list, and quote detail page.

## Changed files

- `app/Views/clients/show.php` — quote list meta (created, follow-up, status date)
- `app/Views/quotes/index.php` — created, follow-up, status date columns
- `app/Views/quotes/show.php` — status with date in header and details
- `config/app.php` (version `1.10.2.1`)

## Database migrations in this release

None.

## Build live bundle

Delta from 1.10.2:

```bash
./scripts/build-live-release.sh live v1.10.2
```

Output: `junktracker_live_releases/live/upload/` (replaces any prior live drop; history stays in git).

## Ops notes

- **Hard refresh** after deploy.
- **Smoke test:** Client → Jobs tab — won quote shows Created and Won dates; open quote detail — subtitle and Status field show won date.

## Related

- Prior release: [live-1.10.2.md](./live-1.10.2.md)
