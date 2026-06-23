# Live Release 1.13.1.0

Date: 2026-06-22

Minor update on [1.13.0.2](./live-1.13.0.2.md).

## Highlights

- **Personal time blocks:** Excluded from dashboard past-due/upcoming agendas, client appointment history, and other operational event feeds. Personal time still appears on the calendar for scheduling/blocking only.

## Database migrations in this release

None.

## Build live bundle

Delta from 1.13.0.2:

```bash
./scripts/build-live-release.sh live v1.13.0.2
```

Output: `junktracker_live_releases/live/upload/` and `…/migrations/`.

## Ops notes

- **Hard refresh** after deploy (version bump in footer).
- **Smoke test:** Dashboard agendas omit personal-time blocks; calendar still shows personal time; client record appointment history excludes personal events.

## Related

- Prior release: [live-1.13.0.2.md](./live-1.13.0.2.md)
