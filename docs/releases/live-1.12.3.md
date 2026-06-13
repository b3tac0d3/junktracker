# Live Release 1.12.3

Date: 2026-05-21

Patch on [1.12.2](./live-1.12.2.md).

## Highlights

- **Sub-contractors:** Fix dashboard/income metrics for completed sub-outs when invoice totals are joined — duplicate PDO parameter binding could skew subcontractor our-cut reporting.

## Database migrations in this release

None.

## Build live bundle

Delta from 1.12.2:

```bash
./scripts/build-live-release.sh live v1.12.2
```

Output: `junktracker_live_releases/live/upload/` (replaces any prior live drop).

## Ops notes

- No special steps after deploy.

## Related

- Prior release: [live-1.12.2.md](./live-1.12.2.md)
