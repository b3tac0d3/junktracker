# JunkMetrix product strategy (mobile & SaaS)

## Vertical-first positioning

**ICP (ideal customer):** Small hauling, junk removal, estate cleanout, and field-service companies with crews on the road.

**Not:** Generic “configure any business type” platform builder.

## Module toggles (v1 SaaS)

Per-business flags in `businesses.module_flags` (JSON). Defaults enable all modules for existing tenants.

| Module key | Nav / features gated |
|------------|---------------------|
| `estate_sales` | Estate sales, estate customers |
| `purchases` | Purchases, purchase quotes |
| `deliveries` | Deliveries |
| `billing` | Invoices, payments, deposits |
| `networking` | Networking directory |
| `subcontractors` | Sub-out tracking |

Helpers: `business_module_enabled('estate_sales')`, `business_module_flags()`.

## Terminology

`businesses.label_job` — UI label only (default `Job`). Future: Work Order, Visit.

Helper: `business_job_label()`.

## Role templates

| Role | Mobile v1 |
|------|-----------|
| `punch_only` | Punch board, today schedule, job read, calendar |
| `general_user` | Above + job status, search, notifications |
| `admin` | Full web admin; mobile same as general_user for v1 |

## SaaS model

- **Multi-tenant on one deploy** — `business_id` isolation already in schema
- **Self-serve signup** — future phase (billing, onboarding wizard)
- **Do not build yet:** industry picker, workflow builder, plugin marketplace

## Mobile scope discipline

v1 mobile = field crew. Full CRM/billing stays web until customer pull justifies it.

## Expansion (after traction)

Adjacent trades sharing job + crew + quote + invoice core:

- Property cleanout / preservation
- Light hauling / demo debris
- Small contractors with job-based billing

Expand when paying customers request it — not speculatively.

## App Store messaging

**Title:** JunkMetrix — Crew & Dispatch  
**Subtitle:** Punch clock, jobs, and schedule for hauling teams

## Related docs

- [API v1](./api-v1.md)
- [Dev accounts](./dev-accounts-setup.md)
- [Beta distribution](./beta-distribution.md)
