# Purchase Prospects module (future build)

Saved for when you're ready to implement. Separates early-stage purchase deals from committed **Purchases** (pending → active → complete).

## Problem today

- **`prospect` is a purchase status** alongside pending/active/complete/cancelled.
- Dashboard **Purchase Prospects** panel queries `purchases` where status is **not** complete/cancelled — so pending/active purchases appear too, not just real prospects.
- No **viewed** tracking, no dedicated **follow-up schedule**, no **convert to purchase** / **mark lost** workflow separate from the purchase record.
- Quotes already have a better pattern: `next_follow_up_at`, statuses, convert to purchase/job/estate sale.

## Goal

A **Purchase Prospects** pipeline for deals you're working or haven't reviewed yet. Reminders until converted or lost. Dashboard shows this queue (same card location as today). **Purchases** list only shows committed orders (no prospect status).

---

## Data model

### New table: `purchase_prospects`

| Column | Purpose |
|--------|---------|
| `id`, `business_id`, `client_id` | Standard |
| `title` | Deal name (e.g. "Smith estate contents") |
| `status` | `open`, `working`, `lost`, `converted` |
| `contact_date` | First contact |
| `next_follow_up_at` | When to nudge again (DATETIME, like quotes) |
| `viewed_at` | First time someone opened detail (NULL = "not viewed") |
| `estimated_price` | Optional ask/offer amount |
| `notes` | Free text |
| `lost_reason` | When status = lost |
| `converted_purchase_id` | FK when converted |
| `created_by`, `updated_by`, audit timestamps, soft delete | Match purchases pattern |

**Status meanings:**

- **open** — new, not yet reviewed (`viewed_at` NULL) or not actively worked
- **working** — actively pursuing (user marked or auto when follow-up set / note added)
- **converted** — became a real purchase (link `converted_purchase_id`)
- **lost** — dead deal; stop all reminders

### Change `purchases.status`

Remove **`prospect`** from allowed values. Purchases start at **`pending`** (or **`active`** if you prefer).

Migration steps:

1. Create `purchase_prospects` table.
2. **Backfill:** `INSERT INTO purchase_prospects (...) SELECT ... FROM purchases WHERE status = 'prospect' AND deleted_at IS NULL`.
3. For each migrated row: either **delete** the old purchase row or set status to `pending` only if it was mistakenly a real PO (default: move to prospects, soft-delete old purchase row if it was never a real order).
4. Alter `purchases.status` ENUM / form select values — drop `prospect`.
5. Remove `prospect` from `FormSelectValue` purchase_status defaults and admin config.

**Live transition:** One current prospect on live — run migration; verify row appears in new module; confirm no duplicate purchase row.

---

## UI / navigation

### Nav (Sales section, financial access)

- **Purchase Prospects** → `/purchase-prospects` (new index)
- **Purchases** → `/purchases` (unchanged, no prospect filter)

Quick add: **Add Purchase Prospect** in nav dropdown (like Add Purchase).

### Index (`/purchase-prospects`)

- Filters: status (open/working/all active), unviewed only, overdue follow-up
- Sort: follow-up date, contact date, created
- Summary counts: open, working, overdue follow-up, unviewed
- Row badges: **New** (unviewed), **Follow-up due** (overdue)

### Detail / form

- Client, title, contact date, estimated price, notes
- **Next follow-up** (datetime-local, like quotes)
- Actions:
  - **Mark as working**
  - **Convert to purchase** → creates `purchases` row (status `pending`), copies client/title/notes/dates, sets `converted_purchase_id`, redirects to new purchase
  - **Mark lost** → reason optional, closes reminders
- On first view: set `viewed_at` (or explicit "Mark reviewed" button)

### Purchases module cleanup

- Remove prospect from status dropdown on purchase form/index summary card
- Default new purchase status: `pending`
- Index summary: 4 columns (pending, active, complete, cancelled) instead of 5

---

## Dashboard (same card, better data)

Replace `DashboardSummary::purchaseProspects()` to query **`purchase_prospects`**:

- Include: `status IN ('open','working')`
- Order: unviewed first, then overdue `next_follow_up_at`, then oldest contact
- Link card to `/purchase-prospects` (not `/purchases`)
- Meta line: client · status · follow-up date or "Not viewed"

---

## Follow-up reminders (until completed)

Mirror **quotes** pattern:

1. **`next_follow_up_at`** on each prospect
2. **Nav notifications** — overdue / due-today follow-ups (`NavNotifications`, badge in header)
3. **Events calendar** — show follow-ups on calendar (`EventFeed`, like quote follow-ups)
4. **Optional task auto-create** — keep existing "create follow-up task" on form; link task to prospect id (new `purchase_prospect_id` on tasks if needed, or generic note in task body)

**Recurring nudge (optional v2):** If follow-up date passes with no action, auto-advance by N days and keep notifying until converted/lost.

---

## Code touchpoints (implementation checklist)

| Area | Change |
|------|--------|
| `database/migrations/..._purchase_prospects.sql` | New table + backfill + enum change |
| `app/Models/PurchaseProspect.php` | CRUD, list, convert, mark lost, dashboard query |
| `app/Models/Purchase.php` | Remove prospect from statusOptions default; update summaries |
| `app/Controllers/PurchaseProspectsController.php` | index, create, show, edit, convert, lost |
| `app/Views/purchase-prospects/` | index, form, show |
| `app/Models/DashboardSummary.php` | `purchaseProspects()` → new table |
| `app/Views/home/index.php` | Link + display fields |
| `app/Views/layouts/main.php` | Nav link |
| `routes/web.php` | Routes |
| `app/Models/NavNotifications.php` | Overdue prospect follow-ups |
| `app/Models/EventFeed.php` | Calendar events |
| `app/Views/purchases/index.php` | Drop prospect summary column |
| `app/Views/purchases/form.php` | Drop prospect status option |
| `FormSelectValue` / migrations | Remove prospect from purchase_status |

---

## Suggested build order

1. Migration + model + backfill (including live prospect)
2. Index + create + edit + show (viewed_at on show)
3. Convert to purchase + mark lost
4. Dashboard + nav
5. Nav notifications + calendar feed
6. Remove prospect from purchases UI and status enum
7. Smoke test: create prospect → dashboard → follow-up reminder → convert → appears under Purchases only

**Effort (rough):** 3–5 days for MVP (CRUD, convert/lost, dashboard, follow-up notifications); +1 day for calendar + task linking.

---

## Open decisions (confirm before build)

1. **After convert:** soft-delete original prospect row or keep as `converted` with link? (Recommend: keep as `converted` for history.)
2. **Migrated purchase rows:** delete old `status=prospect` purchase records after backfill? (Recommend: yes, if they were never real POs.)
3. **Default follow-up interval** when creating prospect (e.g. +3 days) — auto-fill like quotes?
4. **Financial access only** — same gate as Purchases (`require_financial_access`)?

---

## Related files (today)

- `app/Models/DashboardSummary.php` — `purchaseProspects()` (queries purchases incorrectly broadly)
- `app/Views/home/index.php` — dashboard card
- `app/Models/Purchase.php` — status includes `prospect`
- `app/Models/Quote.php` — follow-up + conversion pattern to copy
