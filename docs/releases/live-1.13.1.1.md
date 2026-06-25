# Live Release 1.13.1.1

Date: 2026-06-25

Patch on [1.13.1.0](./live-1.13.1.0.md).

## Highlights

- **Bug fix:** Create Employee no longer 500s — fixed SQL syntax error in `Employee::create()`.
- **Search — jobs:** Past/complete jobs included in global search, mobile job search, and sale/time-entry job typeahead; job status shown on results and client appointment history.
- **Search — clients:** Clients can match when a linked job title, ID, or city matches the query (shows “Job” match tag).

## Database migrations in this release

None.

## Build live bundle

Delta from 1.13.1.0:

```bash
./scripts/build-live-release.sh live v1.13.1.0
```

Output: `junktracker_live_releases/live/upload/` and `…/migrations/`.

## Ops notes

- **Hard refresh** after deploy (footer should show **1.13.1.1**).
- **Smoke test:** Admin → Employees → Add Employee saves successfully; global search for a completed job shows status; client search matches via job title when applicable.

## Related

- Prior release: [live-1.13.1.0.md](./live-1.13.1.0.md)
