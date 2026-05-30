# Live Release 1.10.3.0

Date: 2026-05-30

Patch on [1.10.2.1](./live-1.10.2.1.md).

## Highlights

- **Dashboard:** Profit and Total Income cards show MTD alongside YTD; net uses payments-based service income.
- **Tasks:** Client name on its own line (not baked into title); calendar/search/purchase follow-ups updated.
- **Detail tabs:** After save on tabbed screens (client, job, purchase, estate sale, purchase quote), return to the same tab.
- **Clients:** Jobs tab order is Quotes → Jobs → Estate Sales; estate sales list on client profile.
- **Navigation:** Purchases group (Purchases + Purchase Quotes) moved out of Sales in the sidebar.
- **Search:** Client results show recent appointment history (jobs, events, quote follow-ups, deliveries).

## Database migrations in this release

None.

## Build live bundle

Delta from 1.10.2.1:

```bash
./scripts/build-live-release.sh live v1.10.2.1
```

Output: `junktracker_live_releases/live/upload/` (replaces any prior live drop).

## Ops notes

- **Hard refresh** after deploy (CSS/JS cache bust via app version).
- **Smoke test:** Dashboard MTD/YTD on Profit and Total Income; search a client with scheduled jobs — appointment history appears; save on a job sub-tab — same tab stays active; sidebar shows Purchases group.

## Related

- Prior release: [live-1.10.2.1.md](./live-1.10.2.1.md)
