# Live Release 1.9.2.1-beta

Date: 2026-05-21

## Highlights

- **Estate sales:** Tab bar restyled with per-tab accent colors, icon tiles, and count badges.
- **Layout:** Sidebar auto-hides on iPad and medium screens (below 1200px); tap outside to close.
- **Layout:** Fixed main content shifting off-screen when the sidebar is collapsed on tablet.

## Changed files

- `app/Views/estate-sales/show.php`
- `public/assets/css/jt-theme.css`
- `public/assets/js/scripts.js`
- `config/app.php` (version `1.9.2.1-beta`)
- `docs/deploy-checklist.md`, `docs/releases/live-1.9.2.1-beta.md`
- `patches/live-1.9.2.1-ui-tablet-sidebar.patch` (optional hotfix apply)

## Database migrations in this release

No new database migrations are required for 1.9.2.1-beta.

## Patch-only deploy (FTP / manual upload)

From the project root:

`patch -p1 < patches/live-1.9.2.1-ui-tablet-sidebar.patch`

Or copy the files listed above from this bundle’s `upload/` tree.

## Ops notes

- Smoke test: open an estate sale — confirm tab styling; resize to iPad width — sidebar hidden by default, hamburger opens overlay, content uses full width.
