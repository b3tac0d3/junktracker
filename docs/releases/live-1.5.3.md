# Live release 1.5.3

**Version:** `1.5.3` (tag `v1.5.3`)  
**Type:** patch (bugfixes + small features)

## Summary

- **Client referrals** — Optional “Referred by” on client create/edit (autosuggest), “Referrals sent” on client detail and edit, linked referrer on referred clients. Requires DB column (see migrations).
- **Task details** — Linked job/purchase/client shows human-readable names, phone, and links instead of raw type/ID.
- **Deliveries (production crash)** — `client_deliveries.address_line2` is optional in code when the column is missing; fixes 500 on `/deliveries` and calendar delivery search on older schemas.
- **Client edit** — Referral fields always visible; “Referrals sent” list on edit when applicable.

## Database migrations (run on live, in order, once)

Apply only migrations not already on production:

| File | Purpose |
|------|---------|
| `database/migrations/2026-03-31_client_referrals.sql` | `clients.referred_by_client_id` + FK for referrals |
| `database/migrations/2026-03-27_deliveries_address_need_schedule.sql` | `client_deliveries.address_line2` + `scheduled_at` nullable (if not applied) |

If referrals migration is skipped, referral fields save only after it is applied. If deliveries migration is skipped, 1.5.3 still runs; line 2 on deliveries will not persist until the column exists.

## Build live bundle

From repo root, after committing and tagging `v1.5.3`:

```bash
./scripts/build-live-release.sh junktracker_live_1.5.3 v1.5.2
```

Adjust the previous tag (`v1.5.2`) to whatever was last deployed on the server. Output: `junktracker_live_releases/junktracker_live_1.5.3/upload/` and `…/migrations/`.

## Smoke test

- `/deliveries` loads without error.
- Clients: create/edit shows “Referred by”; client with referrals shows list on show + edit.
- Tasks linked to a job: detail page shows job + client links.
- Calendar / event feed with delivery search does not error if `address_line2` is absent.

## Rollback

Redeploy prior upload bundle and previous `config/app.php` version string. DB migrations are forward-only; do not drop columns unless you have a deliberate rollback script.
