# Live Release 1.9.8

Date: 2026-05-25

## Highlights

### Dashboard redesign
- **Action-first layout:** Greeting header, full-width **Work in progress** calendar agenda (grouped by day), **Time** and **My tasks** side by side below.
- **Calendar agenda:** Jobs, deliveries, quote follow-ups, purchase quotes, estate sales, tasks, and appointments — same sources as the Events calendar.
- **Financial KPIs:** All eight tiles restored (Sales, Service, Total Income, Purchases, Expenses, Estate Sales, Payments Due / Past Due, Profit YTD) plus expanded 3-month chart.
- **Payments Due / Past Due:** Receivables tile shows total open balance and past-due balance.

### Purchase quotes (from 1.9.7.1)
- New pipeline at `/purchase-quotes` with offer history, contact log, convert to purchase / mark lost, and calendar follow-ups.

### Calendar & scheduling (from 1.9.7.1)
- Click a time slot in day/week view → pick type → create form with date/time pre-filled.
- New `/events/create` form for appointments.
- Deliveries from calendar default to **Scheduled**.
- **Events** top-level sidebar link under Dashboard.

### Client & admin (from 1.9.7.0 / 1.9.7.1)
- Client family members, detail tabs (Details | Jobs | Financial | Transactions | Contacts).
- Job and purchase detail tabs.
- Business Admin hub tile reorder.
- **Last Contact** on client Details tab.

### Tasks
- **Add Task** owner defaults to the current user.

## Changed files (1.9.8 delta)

- `app/Controllers/TasksController.php` — default owner on create
- `app/Models/DashboardSummary.php` — upcoming schedule agenda, receivables past due
- `app/Views/home/index.php` — dashboard layout and KPIs
- `app/Views/tasks/form.php` — owner prefill
- `public/assets/css/jt-theme.css` — dashboard agenda and layout styles
- `config/app.php` (version `1.9.8`)

## Database migrations in this release

Run in filename order (idempotent). Skip any already applied on your server.

1. `database/migrations/2026-05-24_client_family_members.sql` — if not yet applied (1.9.7.0)
2. `database/migrations/2026-05-25_purchase_quotes.sql` — **required** for purchase quotes module

## Build live bundle

If live is on **1.9.6.1** or earlier (1.9.7.x not yet deployed):

```bash
./scripts/build-live-release.sh junktracker_live_1.9.8 v1.9.6.1
```

If live already has **1.9.7.1** (dev/staging only):

```bash
./scripts/build-live-release.sh junktracker_live_1.9.8 v1.9.7.1
```

Output: `junktracker_live_releases/junktracker_live_1.9.8/upload/` and `…/migrations/`.

## Ops notes

- **Migrations first:** Run SQL from the bundle `migrations/` folder before uploading code.
- **Hard refresh** after deploy so dashboard CSS loads.
- **Smoke test:** Dashboard — Work in progress agenda, Time punch, My tasks, all eight KPI tiles, chart.
- **Smoke test:** Purchase Quotes — create, follow-up on calendar, convert / mark lost.
- **Smoke test:** Calendar — slot click → create with pre-filled time; Events nav link.
- **Smoke test:** Add Task — owner pre-filled to current user.

## Related

- Prior release: [live-1.9.7.1.md](./live-1.9.7.1.md) (dev checkpoint, folded into 1.9.8 live)
- [live-1.9.7.0.md](./live-1.9.7.0.md), [live-1.9.6.1.md](./live-1.9.6.1.md)
