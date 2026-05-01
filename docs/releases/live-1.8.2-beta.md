# Live Release 1.8.2-beta

Date: 2026-05-01

## Highlights

- Excluded deactivated jobs from the Events calendar feed.
- Fixed quote detail service type display to use title-cased labels instead of lowercase raw values.
- Fixed quote detail client phone loading and clickable phone display.

## Database migrations in this release

No new database migrations are required for 1.8.2-beta.

## Ops notes

- Verify deactivated jobs no longer appear on calendar views.
- Verify quote detail page shows `Service Type` in label case (e.g., `Junk Removal`).
- Verify quote detail phone number appears and is tap-to-call/click-to-call.
