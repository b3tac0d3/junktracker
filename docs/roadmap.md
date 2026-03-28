# JunkTracker roadmap

Prioritized themes and feature ideas. Order within each section is not strict; pick based on business impact and dependencies.

## Client-facing & revenue

- **Branded PDF or “print-ready” pack** for estimates/invoices (logo + typography already in app) so “send to client” is not only browser print.
- **Simple client portal (read-only)** — approve estimate, see invoice balance, download PDF — cuts email back-and-forth and supports “eventually send to clients” properly.
- **Email from the app** (estimate sent, invoice sent, payment receipt) using existing mail config; a few templates completes the billing loop.

## Field & crew workflow

- **Job checklist / close-out** (photos optional later): truck loaded, site clean, signature — supports dispatch → complete without relying on memory.
- **Notifications or daily digest** (tomorrow’s jobs, open punches, overdue invoices) — email or in-app — reduces “did anyone punch out?”

## Money & reconciliation

- **Payment matching & deposit view** — mark which bank deposit covers which payments; helps when you are not 1:1 with Stripe.
- **COGS / margin view** tied to purchases + sales — a single “job or period margin” report for junk/resale.
- **Subcontracted jobs tracking** — jobs fulfilled by third-party providers (workflow TBD).
- **Inventory lot purchasing** — estates, storage units, mixed lots as resale inventory expenses (workflow TBD).

## Admin & quality

- **Audit log for money moves** — invoice status, payment create/edit/delete, punch adjustments — trust and debugging when multiple people use the system.
- **Backups / export** — scheduled DB dump reminder or one-click CSV export of clients + open AR for disaster recovery.

## Technical (low ceremony, high payoff)

- **Google Calendar sync** — phased plan and Phase 1 setup (OAuth, keys): see [google-calendar-sync.md](./google-calendar-sync.md).
- **Delta live bundles + version tags** — keep current practice; add a one-line **deploy checklist** in-repo (migration order, `public/uploads` permissions) so production deploys stay boring.
