# JF Aesthetics — Inventory Module Spec

Oct 14, 2026 · @Rohaan · Source of truth for the Inventory build. Supersedes the PDF version; adds the codebase-review decisions (A–N) and the real table/model names.

## Overview

A full Inventory module replaces the Medicines Store. It tracks every stock item: medicines, injections, syringes, consumables and retail products. Stock comes in through purchasing and goes out through invoices, returns and adjustments.

**Stack:** the existing JF Aesthetics app (Laravel 8, Livewire 2, Blade, MySQL/MariaDB), hosted on PKWebHost cPanel. Tests run on in-memory SQLite. It plugs into the existing Invoices, Services, Patients and RBAC modules.

## Confirmed decisions

| # | Topic | Decision |
|---|---|---|
| 1 | Service consumables | Each service has a fixed recipe; staff can edit, remove or add items per invoice |
| 2 | When stock is deducted | At invoice creation |
| 3 | Billing consumables | Separate invoice lines. Each recipe item has a **billable** flag: non-billable items (gloves, cotton) are deducted but not charged |
| 4 | Invoice model | One invoice table holds service, product and custom lines; product-only invoices allowed |
| 5 | Purchasing | Saved suppliers, P.O, partial deliveries, supplier bills, payments and payables |
| 6 | Batch and expiry | Tracked for every item; earliest-expiry-first deduction; expiry alerts |
| 7 | Units | Pieces only for now; schema leaves room for pack conversion later |
| 8 | Locations | One store (JF Aesthetics); no branch or location column |
| 9 | Existing data | Start fresh; Medicines Store removed |
| 10 | Multi-session services | Recipe qty × sessions at invoice creation (see J) |
| 11 | Insufficient stock | Warn and allow; stock may go negative and is settled later |
| 12 | Returns | Supplier returns and patient returns are in this version |

**Out of scope:** multiple stores, pack-to-piece conversion, barcode scanning, tax engine. The schema must not block adding any of these later.

## Codebase-review decisions (A–N)

| # | Topic | Decision |
|---|---|---|
| A | Old `stock_movements` table | It exists with an incompatible shape (`medicine_id`, `change`, `reason`). It is **dropped and recreated** with the spec's columns. |
| B | Product sales during the build | No gap. Phase 1 replaces the invoice form's "Medicine" line type with a **Product** line (picks from `inventory_items`, deducts via `StockService::stockOut`, warns on shortfall, allows negative). Recipes and child lines arrive in Phase 3. |
| C | Medicines references | Every reference (sidebar, header bell, dashboard low-stock widget, quick-search map, unused `gest` layout, invoice PDF, seeders, tests) is repointed to Inventory in Phase 1. |
| D | `invoice_items.medicine_id` | Kept as an **unused nullable column** (no doctrine/dbal for dropping columns on SQLite). Its foreign key to `medicines` is dropped. |
| E | Scheduler | `app/Console/Kernel::schedule()` drives `inventory:verify` (weekly) and the alert builder (daily). The owner adds the cPanel cron for `php artisan schedule:run`. |
| F | `created_by` | Every new table stores `created_by` as a **foreign key to `users.id`, null on delete** (same as `invoices.created_by`). |
| G | Permission naming | The app stores permissions as `(module, action)` pairs. All inventory permissions use module **`inventory`** with the actions listed under Permissions. |
| H | Roles | There are six roles: Super Admin, Admin, Accountant, Doctor, Receptionist, Nurse. Mapping under Permissions. |
| I | Invoice edit | Editing currently deletes and recreates all items and payments. Phase 3 must update lines **in place** (stable ids) so `parent_line_id`, `stock_movements.reference_line_id` and `patient_return_items.invoice_line_id` stay valid, and return-credit/refund payment rows survive an edit. |
| J | Sessions | `invoice_items.session_number` means **"number of sessions in the package"** (confirmed from live data: peaks at 3 and 5, no increasing 1-2-3 sequences per patient). It is the recipe multiplier; NULL = 1. **Price is not multiplied** (package pricing). UI label is **"No. of Sessions"**; column name unchanged. Deducting the full package at invoice creation is intended; unused items come back via a patient return with Restock. |
| K | Cancel | "Cancel" an invoice = the existing password-gated **soft delete**. Delete now calls `StockService::reverse()` first so stock goes back. The record stays soft-deleted. |
| L | Custom lines | `line_type` has a third value, **`custom`**: no recipe, no stock effect, priced as typed. |
| M | Return credit / refund rows | `invoice_payments` gets an entry-type column in Phase 4 (payment / return credit / refund); refunds are stored as negative amounts. |
| N | Numbering | Invoices keep their current `yy + max(id)+1` scheme. New documents use `document_sequences`: `PO-2600001`, `GRN-2600001`, `SP-`, `SR-`, `PR-`, `ADJ-` (two-digit year + 5-digit counter, counter resets each year). |

## Existing tables and models this module touches

| Concept | Table | Model |
|---|---|---|
| Invoice | `invoices` | `App\Models\Invoice` (totals are computed accessors: `grand_total` = Σ `after_discount`) |
| Invoice line | `invoice_items` | `App\Models\InvoiceItem` |
| Payment History | `invoice_payments` | `App\Models\InvoicePayment` |
| Service | `services` | `App\Models\Service` |
| Patient | `patients` | `App\Models\patient` |
| User | `users` | `App\Models\User` |
| Permissions | `permissions` (module, action) + `role_permissions` | `App\Models\Permission`, `App\Models\Role` |
| Removed | `medicines`, old `stock_movements` | `App\Models\medicine` (deleted), `App\Livewire\Admins\Medicinestore` (deleted) |

The legacy `payments` table (old hospital bills) is unrelated and untouched.

## Navigation and screens

The sidebar item **Medicines Store** becomes **Inventory** (route prefix `/admin/inventory`, component namespace `App\Http\Livewire\Admins\Inventory`). Each tab is a Livewire component with list, filter, create, view and edit.

| Tab | List shows | Key actions | Phase |
|---|---|---|---|
| Dashboard | Stock value, low-stock count, expiring-soon count, negative-stock count, payables total | Click-through to each list | 5 |
| Items | Code, name, category, on hand (with usable qty when different, e.g. "15 (5 usable)"), cost, sale price, status badges (low, negative, expired, expiring) | Add/edit item, view batches, view movement ledger | 1 |
| Categories | Name, item count | Add/edit/deactivate | 1 |
| Suppliers | Name, phone, balance owed | Add/edit, view ledger, record payment | 2 |
| Purchase Orders | P.O no, supplier, date, total, status | Create, send (mark ordered), receive, cancel, print P.O | 2 |
| Receiving (GRN) | GRN no, P.O no, supplier, bill no, date, total | Receive against P.O or direct receive, print GRN | 2 |
| Supplier Payments | Date, supplier, amount, mode, linked bill | Record, edit, delete (permission-gated) | 2 |
| Supplier Returns | Return no, supplier, date, total | Create from a GRN, print debit note | 4 |
| Invoices | Invoices that contain product lines (filter: product-only / with services) | New product invoice; opens the same invoice form | 3 |
| Patient Returns | Return no, invoice, patient, date, amount | Create from an invoice, choose refund or credit | 4 |
| Adjustments | Adj no, date, reason, lines | Opening stock (Phase 1); damaged, expired, count correction, other (Phase 4) | 1 / 4 |
| Reports | Report list | Filter, print, export CSV | 5 |

**Services screen** gets a new section: **Recipe** (item, qty per session, billable). Phase 3.

**Invoice form** (existing) gets product lines (Phase 1) and auto-filled recipe lines (Phase 3).

## Database schema

All stock changes go through one ledger, `stock_movements`. Quantities are whole pieces (signed integers). Money is `decimal(12,2)`. Every table has `id`, timestamps and `created_by` (FK `users`, null on delete); master tables also get soft deletes.

### Master data

| Table / Model | Columns |
|---|---|
| `inventory_categories` / `InventoryCategory` | name, is_active |
| `inventory_items` / `InventoryItem` | code (unique), name, category_id, unit (default `piece`), cost_price (last received cost), sale_price, low_stock_threshold (default 10), default_billable (bool), qty_on_hand (cached, may be negative), is_active |
| `suppliers` / `Supplier` | name, contact_person, phone, email, address, opening_balance, is_active |
| `item_batches` / `ItemBatch` | item_id, batch_no, expiry_date (nullable — items without expiry sort last in FEFO), unit_cost, qty_received, qty_remaining, source (`grn`, `adjustment`, `patient_return`), source_line_id. Index on (item_id, expiry_date) |

### Ledger

| Table / Model | Columns |
|---|---|
| `stock_movements` / `StockMovement` | item_id, batch_id (nullable), type, qty (signed), unit_cost, reference_type + reference_id (morph to the document), reference_line_id, note, moved_at |

Movement types: `grn_in`, `invoice_out`, `invoice_reversal`, `supplier_return_out`, `patient_return_in`, `adjustment_in`, `adjustment_out`, `shortfall_settle`, `reversal` (generic undo of a non-invoice document, written by `StockService::reverse()`).

### Purchasing and payables (Phase 2/4)

| Table | Columns |
|---|---|
| `purchase_orders` | po_no (unique), supplier_id, order_date, expected_date, status, subtotal, discount, other_charges, total, notes |
| `purchase_order_items` | purchase_order_id, item_id, qty_ordered, qty_received (cached), unit_cost, line_total |
| `goods_receipts` | grn_no (unique), purchase_order_id (nullable for direct receive), supplier_id, supplier_bill_no, receive_date, due_date, subtotal, discount, other_charges, total, amount_paid (cached), payment_status (`unpaid`, `partial`, `paid`), notes |
| `goods_receipt_items` | goods_receipt_id, purchase_order_item_id (nullable), item_id, batch_no, expiry_date, qty, unit_cost, line_total, batch_id |
| `supplier_payments` | payment_no, supplier_id, payment_date, amount, mode (`cash`, `bank_transfer`, `cheque`), reference_no, notes |
| `supplier_payment_allocations` | supplier_payment_id, goods_receipt_id, amount. Unallocated remainder = advance to supplier |
| `supplier_returns` | return_no, supplier_id, goods_receipt_id (nullable), return_date, reason, total, notes |
| `supplier_return_items` | supplier_return_id, item_id, batch_id, qty, unit_cost, line_total |

### Services, invoices and patient returns

| Table | Columns |
|---|---|
| `service_items` (recipe, Phase 3) | service_id, item_id, qty_per_session, is_billable. Unique (service_id, item_id) |
| `invoice_items` (existing, columns added in Phase 1) | `line_type` (`service`, `product`, `custom`), `inventory_item_id` (nullable), `parent_line_id` (nullable, ties a consumable to its service line), `is_billable` (default true), `unit_cost` (snapshot for margin). Existing rows are backfilled: `service_id` set → `service`, otherwise → `custom` (old medicine lines become `custom`: they keep their name and price, have no stock effect) |
| `invoice_payments` (existing, Phase 4) | add an entry-type column (`payment`, `return_credit`, `refund`) — see M |
| `patient_returns` (Phase 4) | return_no, invoice_id, patient_id, return_date, reason, total, settlement (`refund`, `invoice_credit`), refund_mode, notes |
| `patient_return_items` (Phase 4) | patient_return_id, invoice_line_id (→ `invoice_items.id`), item_id, qty, unit_price, line_total, restock (bool), batch_id |

### Adjustments and numbering

| Table / Model | Columns |
|---|---|
| `stock_adjustments` / `StockAdjustment` | adj_no, adj_date, reason (`opening_stock`, `damaged`, `expired`, `count_correction`, `other`), notes. Soft deletes |
| `stock_adjustment_items` / `StockAdjustmentItem` | stock_adjustment_id, item_id, batch_id (nullable), batch_no, expiry_date, qty (signed), unit_cost |
| `document_sequences` / `DocumentSequence` | doc_type, year, last_number. Unique (doc_type, year). See N |

**Supplier balance owed** = opening_balance + Σ GRN totals − Σ payments − Σ supplier return totals.

## Stock engine rules

One service class, `App\Services\Inventory\StockService`, makes every stock change. No controller or Livewire component writes to `stock_movements`, `item_batches` or `qty_on_hand` directly.

### Core methods

- `stockIn(item, qty, batchNo, expiry, unitCost, reference, lineId, type)`: creates or tops up a batch (same item + batch no + expiry), writes a `+` movement, then settles any shortfall (below).
- `stockOut(item, qty, reference, ?batchId, lineId, type)`: deducts earliest-expiry first, or from the given batch. Returns the allocation list.
- `reverse(reference)`: for every (item, batch) the document has a net movement on, writes the opposite movement, back to the same batches. Running it twice is a no-op. It refuses (throws) if undoing a stock-in would take a batch below zero — i.e. that stock was already used; the fix is a return or an adjustment.
- Every call runs inside `DB::transaction()` with `lockForUpdate()` on the item row.

### Earliest-expiry-first (FEFO)

1. Take batches of the item with `qty_remaining > 0` and expiry today or later (or no expiry).
2. Order by expiry_date ascending (no-expiry last), then created_at.
3. Deduct from each until the qty is covered. One movement per batch touched.
4. Expired batches are never picked automatically. They leave stock only through an `expired` adjustment or a supplier return.

### Negative stock (warn and allow)

1. If batches can't cover the qty, the remainder is written as an `invoice_out` movement with `batch_id = null`. `qty_on_hand` goes negative.
2. The item shows a red **Negative** badge and appears in the negative-stock report.
3. On the next `stockIn` for that item, the shortfall is settled first: the new batch's `qty_remaining` drops by the shortfall, and a pair of `shortfall_settle` movements moves the qty from "no batch" to that batch. Net change to on hand is zero.
4. Cost for a shortfall line uses the item's `cost_price` until it is settled.

### Usable qty, expiry and low-stock status

- **Usable qty** = stock in unexpired batches + the unbatched balance (≤ 0 while a shortfall is unsettled). It equals what FEFO can actually sell. On hand still includes expired stock until it is written off.
- **Expired** (red badge): the item has stock in a batch whose expiry date is before today.
- **Expiring** (amber badge): the item has stock in a batch expiring from today up to today + 30 days inclusive (day 30 flags, day 31 doesn't). A batch expiring today is still usable.
- An item can show both badges.
- **Low** = active item with usable qty ≤ `low_stock_threshold`. The Low badge, the Low-stock filter, the header bell count and the dashboard low-stock widget all use usable qty, so an item whose shelf stock is mostly expired shows as low.
- **Negative** = `qty_on_hand` < 0.

### Warnings in the UI

- Invoice and adjustment forms show available qty beside each line, live.
- On save, if any line exceeds stock, a confirm modal lists the items and the shortfall. **Save anyway** proceeds; **Cancel** returns to the form.
- A line drawn from a batch expiring within 30 days shows an amber note.

### Edit, cancel, delete

- Editing an invoice, GRN, return or adjustment: `reverse(document)`, then re-apply the new lines, in one transaction.
- Cancelling or deleting: `reverse(document)` only. Documents with movements are soft-deleted, never hard-deleted. For invoices, cancel = the existing password-gated delete (K).
- A GRN can't be edited or deleted if its batches were already used. The fix is a supplier return or an adjustment.

### Integrity rules

- `qty_on_hand` = Σ `stock_movements.qty` for the item.
- Σ `qty_remaining` of its batches − unsettled shortfall = `qty_on_hand`.
- `php artisan inventory:verify` checks both and reports mismatches. Scheduled weekly (E).

## Purchasing and supplier payables (Phase 2)

A P.O is a request only: it moves no stock and creates no debt. Stock and the amount owed both come from the GRN (goods received note), so a partial delivery only counts what actually arrived.

### Purchase order

- Fields: supplier, order date, expected date, lines (item, qty, unit cost), discount, other charges, notes.
- Unit cost pre-fills from the item's last cost.
- Only **Draft** P.Os are fully editable. An **Ordered** P.O can have lines added or qty increased; qty can't go below what was received.
- Printable P.O in the JF Aesthetics A4 style, same branding as the invoice PDF.
- Status flow: Draft → Ordered → Partially received → Received; Draft/Ordered → Cancelled only while nothing is received. Status changes automatically on each GRN.

### Receiving (GRN)

1. Open **Receive** on a P.O. Each line shows ordered, already received, and remaining.
2. Enter qty received now (0 to remaining), batch no, expiry date, and unit cost (editable if the bill differs).
3. Enter the supplier's bill no and due date.
4. Save: each line calls `stockIn`, `qty_received` updates on the P.O line, and the P.O status updates.
5. **Direct receive** (no P.O) is allowed for walk-in purchases, with the same form and a supplier picked.
6. Receiving more than ordered is blocked; the user edits the P.O first.

Item `cost_price` updates to the latest received unit cost.

### Supplier payments

- Record payment: supplier, date, amount, mode, reference no.
- Allocate it across that supplier's unpaid GRNs, oldest first by default; the user can change allocations.
- Any unallocated amount stays as an advance and auto-allocates to the next GRN.
- GRN `payment_status` updates to unpaid, partial or paid.

### Supplier ledger

One screen per supplier, running balance by date: Opening balance (debit) · GRN (debit, GRN no + bill no) · Payment (credit) · Supplier return (credit). Print and CSV export for date ranges.

## Service recipes and invoice integration

### Phase 1 (product lines)

- The invoice form's line types are **Service**, **Product**, **Custom / Typed**. "Medicine" is gone.
- A Product line picks an active `inventory_items` row; price = item `sale_price` × qty. Editing the unit price requires `inventory.price_override`; without it the server re-applies the sale price on save.
- Each product line shows available (sellable) stock live. On save, shortfalls open the confirm modal (Save anyway / Cancel).
- Saving deducts via `StockService::stockOut` (FEFO, type `invoice_out`, reference = the invoice, `reference_line_id` = the invoice line). `unit_cost` snapshots the item's `cost_price`.
- Editing an invoice reverses its movements and re-applies the new lines in one transaction. Deleting (= cancel, K) reverses then soft-deletes.
- The "Session" column is relabelled **"No. of Sessions"** (J).

### Phase 3 (recipes and child lines)

Adding a service to an invoice auto-fills its recipe as editable child lines, multiplied by sessions. Stock is deducted when the invoice is saved.

**Recipe (on the Services screen):** lines: item, qty per session, billable (defaults from the item's `default_billable`). Changing a recipe affects new invoices only. Saved invoices keep their own lines.

**Invoice form changes:**

1. **Add service:** the service line appears, then its recipe lines indented under it with `parent_line_id` set. Child qty = qty_per_session × sessions (`session_number`, NULL = 1).
2. Changing the service's sessions recalculates child qty, unless the user has edited that child by hand.
3. Staff can edit a child's qty, remove it, or **Add item used** under a service.
4. **Add product:** a standalone product line (no parent), e.g. a retail sunblock.
5. **Billable** child and product lines are priced at the item's sale price × qty. Line discount works as it does today. Editing the unit price needs `inventory.price_override`.
6. **Non-billable** lines have price 0, are hidden on the patient PDF and invoice view, and still deduct stock.
7. Each line shows available stock live. The negative-stock confirm modal runs on save.
8. A product-only invoice is the same form with no service lines. Doctor is optional for it.
9. Service line price is never multiplied by sessions (J).
10. Edit updates lines in place (I).

**Example: Ramsha, 1 service × 3 sessions** — assumed recipe for "Hifu+MFU+RF+ Fat dissolving Inj": 1 injection and 2 syringes billable, 1 pair of gloves non-billable, per session. Prices are illustrative.

| Line | Type | Qty | Unit price | Shown to patient | Stock out |
|---|---|---|---|---|---|
| Hifu+MFU+RF+ Fat dissolving Inj | Service, 3 sessions | 1 | 135,000 | Yes | none |
| └ Fat dissolving injection | Product, billable | 3 | 4,500 | Yes | 3 |
| └ Syringe 3ml | Product, billable | 6 | 50 | Yes | 6 |
| └ Gloves (pair) | Product, non-billable | 3 | 0 | No | 3 |
| Sunblock SPF 50 | Product, standalone | 1 | 3,200 | Yes | 1 |

**Invoice view and PDF:** child lines print indented under their service, and the Grand Total includes billable lines. Payments, Paid and Unpaid work exactly as today. The dashboard's Revenue counts all billable lines; a new split shows service revenue vs product revenue.

## Returns (Phase 4)

Both return types start from an original document, so quantities, batches and prices are never typed from scratch.

### Supplier returns (damaged, wrong or expired items)

1. Pick the supplier, then the GRN (or pick batches directly for older stock).
2. Choose lines and qty. Max = that batch's `qty_remaining`.
3. Reason: damaged, wrong item, expired, other.
4. Save: `stockOut` from the chosen batch (no FEFO), type `supplier_return_out`, at the batch's unit cost.
5. The return total reduces the supplier balance owed (a credit in the ledger).
6. Print a debit note in JF Aesthetics A4 style.

### Patient returns (unused product)

1. Open an invoice, then **Return items**. Only product lines are returnable; service and custom lines are not.
2. Max qty per line = qty sold − qty already returned.
3. For each line choose **Restock** (goes back to the batch it came from) or **Write off** (damaged or opened; no stock in).
4. Settlement:
   - **Invoice credit:** reduces the invoice's Unpaid. It shows in Payment History as "Return credit" with the return no.
   - **Refund:** the invoice must be paid at least the refund amount. Record the refund mode (cash, bank transfer); it shows in Payment History as a negative entry.
5. Return amount = qty × the line's price after discount.
6. Non-billable lines can be restocked but carry no money.

Returns can be cancelled, which reverses their stock and money effects. They can't be edited after saving.

## Adjustments, reports, alerts and permissions

### Stock adjustments

| Reason | Direction | Batch handling | Phase |
|---|---|---|---|
| Opening stock | In | Enter batch no, expiry, unit cost per line. Used once to load current shelf stock at go-live | 1 |
| Count correction | In or out | Out: pick batch or FEFO. In: enter batch details | 4 |
| Damaged | Out | Pick batch | 4 |
| Expired | Out | Pick from expired batches only | 4 |
| Other | In or out | Note required | 4 |

Deleting an adjustment reverses it (blocked if its stock was already used) and soft-deletes it.

### Reports (Phase 5)

All reports filter by date range, category and item, and offer print and CSV: Stock on hand · Low stock · Negative stock · Expiry (30/60/90 days and expired) · Item movement ledger · Consumption by service · Product sales and margin · Purchases · Supplier payables (0–30, 31–60, 61–90, 90+ aging) · Returns · Adjustments.

### Alerts (Phase 5)

- The header bell gets inventory notifications: low stock, negative stock, batches expiring within 30 days, GRNs past due date.
- A daily scheduled command builds them once a day, so page loads stay fast. (Until then the bell shows a live low-stock count from `inventory_items`.)
- The main Dashboard gets an inventory card row: stock value, low-stock count, expiring-soon count, payables due.

### Permissions

Module **`inventory`** in the `permissions` table. Route middleware: `permission:inventory,<action>`; code: `hasPermission('inventory', '<action>')`.

| Action | Covers |
|---|---|
| `view` | Items, batches, stock lists |
| `manage_items` | Create/edit items and categories |
| `manage_recipes` | Edit service recipes |
| `price_override` | Change unit price on invoice product lines |
| `manage_po` | Create/edit/cancel P.Os |
| `manage_grn` | Receive stock |
| `manage_suppliers` | Create/edit suppliers |
| `supplier_payments` | Record/edit supplier payments |
| `supplier_returns` | Supplier returns |
| `patient_returns` | Patient returns and refunds |
| `manage_adjustments` | Stock adjustments |
| `view_reports` | All inventory reports |
| `view_cost` | See cost prices, stock value and margin |

**Role mapping:**

| Role | Actions |
|---|---|
| Super Admin | all |
| Admin | all |
| Accountant | view, manage_items, manage_po, manage_grn, manage_suppliers, supplier_payments, supplier_returns, patient_returns, manage_adjustments, view_reports, view_cost |
| Doctor | view, manage_recipes |
| Receptionist | view, patient_returns |
| Nurse | view |

The old `medicines_store` permission rows are deleted by `RoleSeeder`.

## Build phases

Each phase ships to production only after its tests pass on a copy of the live database.

1. **Foundation:** migrations (inventory_categories, inventory_items, item_batches, stock_movements, stock_adjustments, stock_adjustment_items, document_sequences, invoice_items columns), `StockService`, categories, items, batches, opening-stock adjustment, item movement ledger, Product line on invoices (B), delete reverses stock (K), "No. of Sessions" label (J), `inventory` permissions. Remove the Medicines Store menu, routes, component, model and tables.
2. **Purchasing:** suppliers, P.O, GRN with partial receiving, supplier payments with allocation, supplier ledger, P.O and GRN PDFs.
3. **Invoice integration:** service recipes, child lines on the invoice form, recipe × sessions, in-place line editing (I), invoice PDF update.
4. **Returns and adjustments:** supplier returns with debit note, patient returns with restock/write-off and refund/credit (M), all adjustment reasons.
5. **Reports, alerts:** all reports, bell notifications, dashboard cards, `inventory:verify` scheduling.

**Before Phase 1 goes live:** take a full database backup (the existing cron backup plus a manual one).

## Acceptance tests

- [ ] Receiving 60 of a 100-qty P.O line sets the P.O to Partially received, adds 60 to stock, and adds the GRN total to the supplier balance
- [ ] Receiving the remaining 40 sets the P.O to Received
- [ ] A 3-session service with a recipe of 2 syringes per session deducts 6 syringes on save
- [ ] With batches A (expiry Dec) and B (expiry Mar), a sale takes from the earlier-expiring batch first
- [ ] An expired batch is never auto-picked
- [ ] Selling 5 with 3 in stock shows the warning; Save anyway leaves on hand at −2
- [ ] Receiving 10 after that leaves on hand 8 and the new batch's remaining 8
- [ ] Editing an invoice from 3 to 1 injection returns 2 to the same batch
- [ ] Cancelling (deleting) an invoice returns all its stock
- [ ] Non-billable lines deduct stock but don't appear on the patient PDF or in the total
- [ ] A product-only invoice saves without a doctor or service
- [ ] A payment of 50,000 against two GRNs of 30,000 each marks the first paid and the second partial
- [ ] A supplier return of 5,000 reduces the supplier balance by 5,000
- [ ] A patient return with Restock puts qty back in its original batch; Write off doesn't change stock
- [ ] Invoice credit reduces Unpaid; a refund shows as a negative entry in Payment History
- [ ] `inventory:verify` reports zero mismatches after all the above
- [ ] A user without `inventory.view_cost` sees no cost or margin anywhere
