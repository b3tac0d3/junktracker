# Live Release 1.9.0.1-beta

Date: 2026-05-21

## Highlights

- **Estate sales:** Split basis dropdown options now include short parenthetical descriptions (e.g. `Split net (% sales − expenses)`).
- **Estate sales:** Help text under split basis uses concrete dollar examples for each option.

## Changed files

- `app/Models/EstateSale.php`
- `config/app.php` (version `1.9.0.1-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.0.1-beta.md`
- `patches/live-1.9.0.1-estate-sale-split-labels.patch` (optional hotfix apply)

## Database migrations in this release

No new database migrations are required for 1.9.0.1-beta.

## Patch-only deploy (FTP / manual upload)

From the project root:

`patch -p1 < patches/live-1.9.0.1-estate-sale-split-labels.patch`

Or copy `app/Models/EstateSale.php` and merge `config/app.php` from this bundle’s `upload/` tree.

## Ops notes

- Smoke test: open an estate sale → **Edit** → confirm split basis list shows parenthetical descriptions and help text updates when you change the selection.
