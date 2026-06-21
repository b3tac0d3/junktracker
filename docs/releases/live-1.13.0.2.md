# Live Release 1.13.0.2

Date: 2026-06-21

Patch on [1.13.0.1](./live-1.13.0.1.md).

## Highlights

- **Bug fix:** Dashboard and calendar no longer 500 when optional address columns (e.g. `client_deliveries.address_line2`) are missing on the database — event feed uses schema-safe column selection.
- **Support Logs:** Admin → Support Logs — unified list for bug reports and update requests at `/admin/support-logs`.
- **Add Client — Next Step:** More post-save redirects (job, quote, purchase, delivery, follow-up task, general task, BOLO).
- **Quote scheduling:** Follow-up task (open-ended, lands on task list) vs meeting (hard calendar date) on quote and purchase-quote forms.
- **Calendar popup:** Address with Google Maps link when clicking calendar events (jobs, deliveries, quotes, tasks, etc.).

## Database migrations in this release

None.

## Build live bundle

Delta from 1.13.0.1:

```bash
./scripts/build-live-release.sh live v1.13.0.1
```

Output: `junktracker_live_releases/live/upload/` and `…/migrations/`.

## Ops notes

- **Hard refresh** after deploy (version bump in footer).
- **Smoke test:** Homepage/dashboard loads; calendar event click shows address; Admin → Support Logs; create client with Next Step redirects; quote form follow-up vs meeting scheduling.

## Related

- Prior release: [live-1.13.0.1.md](./live-1.13.0.1.md)
