# Live Release 1.10.1.1

Date: 2026-05-25

Patch on [1.10.1.0](./live-1.10.1.0.md).

## Highlights

- **Billing buckets:** Dotted 3px divider under Past due, Unpaid, and Paid section headers.

## Changed files

- `public/assets/css/jt-theme.css` — `.billing-bucket-header` border
- `config/app.php` (version `1.10.1.1`)

## Database migrations in this release

None.

## Build live bundle

Delta from 1.10.1.0:

```bash
./scripts/build-live-release.sh junktracker_live_1.10.1.1 v1.10.1.0
```

Output: `junktracker_live_releases/junktracker_live_1.10.1.1/upload/`.

## Ops notes

- **Hard refresh** after deploy.
- **Smoke test:** Billing → Invoices tab — dotted line under each bucket header (Past due, Unpaid, Paid).

## Related

- Prior release: [live-1.10.1.0.md](./live-1.10.1.0.md)
