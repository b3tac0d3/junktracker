# Live Release 1.12.1

Date: 2026-06-08

Patch on [1.12.0](./live-1.12.0.md).

## Highlights

- **Google Calendar:** **Remove all from Google** bulk action on Settings — deletes every JunkTracker-synced event from Google Calendar for the logged-in user (appointments/jobs in JunkTracker are not deleted). Shows synced event count when connected.
- **Nav:** BOLO link order under Sales (BOLO above Estate Sales).

## Database migrations in this release

None.

## Build live bundle

Delta from 1.12.0:

```bash
./scripts/build-live-release.sh live v1.12.0
```

Output: `junktracker_live_releases/live/upload/` (replaces any prior live drop).

## Ops notes

- **Hard refresh** after deploy.
- **Smoke test:** Settings → Google Calendar (connected) → confirm synced count and **Remove all from Google**; disconnect confirm still notes events are not removed unless you use bulk remove first.

## Related

- Prior release: [live-1.12.0.md](./live-1.12.0.md)
