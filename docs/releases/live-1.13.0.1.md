# Live Release 1.13.0.1

Date: 2026-06-18

Patch on [1.13.0](./live-1.13.0.md).

## Highlights

- **Bug fix:** Site admin and mobile API no longer 500 after 1.13.0 deploy — removed incorrect `Core\SchemaInspector` import; class lives in `App\Models`.

## Changed files

- `app/Models/Business.php` — fix SchemaInspector namespace
- `app/Models/ApiToken.php` — same
- `app/Models/DeviceToken.php` — same
- `config/app.php` (version `1.13.0.1`)

## Database migrations in this release

None.

## Build live bundle

Delta from 1.13.0:

```bash
./scripts/build-live-release.sh live v1.13.0
```

Output: `junktracker_live_releases/live/upload/` and `…/migrations/`.

## Ops notes

- **Hard refresh** after deploy (version bump in footer).
- **Smoke test:** `/site-admin/businesses` loads; site admin dashboard and business switcher work.
- If 1.13.0 migrations were not run yet, run all seven from [live-1.13.0.md](./live-1.13.0.md) — this patch does not add new SQL.

## Related

- Prior release: [live-1.13.0.md](./live-1.13.0.md)
