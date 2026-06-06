# Live Release 1.12.0

Date: 2026-06-06

Patch on [1.11.0](./live-1.11.0.md).

## Highlights

- **Sub-contractors:** People → Sub-Contractors; sub out jobs, track sub pay vs your cut, profile tabs (Details / Jobs / Earnings), addresses, assign job from sub profile, referral fee expense preset.
- **Jobs:** Sub Out in Actions menu (second item); enhanced Actions dropdown styling on job, client, and sub detail pages.
- **Nav:** BOLO moved under Sales (bottom of list).
- **Expenses:** Disposal weight optional on job expenses; fix expense update/delete audit when saving general expenses.
- **Appointments:** Optional client on events; client phone in calendar feed, Gmail, and Google Calendar when linked.

## Database migrations in this release

Run on live after deploy:

- `2026-06-07_subcontractors.sql`
- `2026-06-08_subcontractors_address.sql`

## Build live bundle

Delta from 1.11.0:

```bash
./scripts/build-live-release.sh live v1.11.0
```

Output: `junktracker_live_releases/live/upload/` (replaces any prior live drop).

## Ops notes

- Run both subcontractor migrations before using **Sub-Contractors** or **Sub Out**.
- **Hard refresh** after deploy (CSS cache for `jt-actions-menu`).
- **Smoke test:** People → Sub-Contractors; job → Actions → Sub Out; job disposal expense without weight; Settings unchanged from 1.11.0.

## Related

- Prior release: [live-1.11.0.md](./live-1.11.0.md)
