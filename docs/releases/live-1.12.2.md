# Live Release 1.12.2

Date: 2026-06-08

Patch on [1.12.1](./live-1.12.1.md).

## Highlights

- **Google Calendar:** Sync everything on the Events calendar — appointments, personal time, reminders, jobs, tasks (due date), deliveries, quote follow-ups, purchase quote follow-ups, and estate sales. Backfill uses the same rules as the calendar.
- **Dashboard / notifications:** Cache invalidates immediately when business data changes (faster updates after saves); fallback TTL 30s.
- **Tasks:** Fix list row alignment when a task has a linked client (nested link bug).

## Database migrations in this release

None.

## Build live bundle

Delta from 1.12.1:

```bash
./scripts/build-live-release.sh live v1.12.1
```

Output: `junktracker_live_releases/live/upload/` (replaces any prior live drop).

## Ops notes

- **Hard refresh** after deploy (CSS for task list fix).
- After deploy, each connected Google user should run **Settings → Sync upcoming (90 days)** to backfill calendar items that were missing before this release.

## Related

- Prior release: [live-1.12.1.md](./live-1.12.1.md)
