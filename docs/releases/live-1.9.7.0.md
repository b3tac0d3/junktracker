# Live Release 1.9.7.0

Date: 2026-05-25

## Highlights

- **Client family members:** Add follow-up contacts on a client (name, relationship, phone, optional link to an existing client via search). Shown under **Details** on the client page.
- **Client detail tabs:** **Details** | **Jobs** | **Financial** | **Transactions** | **BOLO** (when enabled) | **Contacts** — quotes and jobs separated on Jobs; financial summaries vs transaction lists split; tab URL sync via `?tab=`.
- **Job detail tabs:** **Details** | **Financial** | **Transactions** | **Labor** — same tab pattern as clients and estate sales.
- **Purchase detail tabs:** **Details** | **Financial** | **Sales** | **Tasks**.
- **Client financials:** Service lifetime gross/net with expenses and labor; sales and purchase counts on Financial tab.
- **Business Admin hub:** Reordered tiles — setup first (Business Details, Invoice Item Types, Form Select Values, Activity Log), then Users and Employees.

## Changed files

- `app/Controllers/ClientsController.php` — family CRUD, tab state, financial/quote data
- `app/Controllers/JobsController.php`, `app/Controllers/PurchasesController.php` — tab state
- `app/Models/Client.php` — extended `financialSummary()`
- `app/Models/ClientFamilyMember.php` — family member CRUD and validation
- `app/Models/Job.php` — `laborCostByClient()`
- `app/Models/Quote.php` — client quote list and status summary
- `app/Views/clients/show.php`, `app/Views/clients/family_member_form.php`
- `app/Views/jobs/show.php`, `app/Views/purchases/show.php`
- `app/Views/admin/index.php` — hub tile order
- `public/assets/css/jt-theme.css` — tab accents for clients, jobs, purchases
- `routes/web.php` — family member routes
- `config/app.php` (version `1.9.7.0`)
- `docs/deploy-checklist.md`, `docs/roadmap.md`, `docs/releases/live-1.9.7.0.md`

## Database migrations in this release

Run in filename order (idempotent):

1. `database/migrations/2026-05-24_client_family_members.sql` — `client_family_members` table (**required** for family contacts; section stays hidden until applied)

## Build live bundle

```bash
./scripts/build-live-release.sh junktracker_live_1.9.7.0 v1.9.6.1
```

Output: `junktracker_live_releases/junktracker_live_1.9.7.0/upload/` and `…/migrations/`.

## Ops notes

- **Migrations first:** Run the family members SQL before uploading code if you want family contacts on day one.
- **Hard refresh** after deploy so updated CSS loads.
- **Smoke test:** Client → **Details** → add family member; search and link an existing client.
- **Smoke test:** Client tabs — Jobs shows quotes + jobs; Financial vs Transactions; Contacts log.
- **Smoke test:** Job and Purchase detail pages — tab navigation and `?tab=` URLs.
- **Smoke test:** Business Admin — confirm tile order (setup row, then Users/Employees).

## Related

- Prior release: [live-1.9.6.1.md](./live-1.9.6.1.md)
