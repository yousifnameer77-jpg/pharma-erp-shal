# Pharma ERP — Backend (Laravel 11 + PostgreSQL)

## Modules delivered so far

1. **Foundation** — Auth, User Management, RBAC
2. **Inventory Management** — Products & Catalog, Batches, Stock, Stock Movements
3. **Purchasing** (+ a minimal **Accounting** foundation) — Purchase Requests,
   Purchase Orders, Goods Receipts, Purchase Invoices, Supplier Payments,
   Chart of Accounts, Journal Entries
4. **Sales** — Customers, Sales Invoices, Returns, Customer Payments, Customer Balance
5. **Accounting** — Expenses (the last thing that needed to auto-post),
   General Ledger, Cash, Banks, Receivables & Payables aging, Profit & Loss,
   Balance Sheet
6. **Branch & Warehouse management** — read-only Branches, full Warehouse
   CRUD, plus `company_id`/`user_role_id` additions to `UserResource` — all
   added to support the companion Next.js frontend
7. **Sales reports** — daily / monthly / by-branch sales breakdowns, for the
   frontend's Reports page

See "What's in this module" further down for Foundation, "Inventory
Management module" for the second pass, "Purchasing module" for the third,
"Sales module" for the fourth, "Accounting module" for the fifth, "Branch &
Warehouse management, and frontend-support additions" for the sixth, and
"Sales reports" below for what was added in this pass.

This is a hand-authored application layer only — this sandbox has no network
access to Packagist, so `composer install` could not be run here. Everything
under `app/`, `database/`, and `routes/api.php` is real, lint-checked Laravel
11 code; you just need to drop it into a scaffolded Laravel install once and
pull the vendor packages on a machine that *does* have internet access.

### One-time setup (on your machine)

```bash
# 1. Scaffold a real Laravel 11 project
composer create-project laravel/laravel pharma-erp-backend "^11.0"
cd pharma-erp-backend

# 2. Add Sanctum (API token auth) and wire it up
composer require laravel/sanctum
php artisan install:api   # publishes the personal_access_tokens migration

# 3. Copy the files from this delivery over the fresh install, overwriting:
#    app/, database/migrations/, database/seeders/, routes/api.php, bootstrap/app.php
#    (composer.json/.env.example are reference copies — merge by hand if you
#    changed the scaffolded project's composer.json already)

# 4. Configure the database
cp .env.example .env   # then edit DB_* to match your PostgreSQL instance
php artisan key:generate

# 5. Migrate and seed
php artisan migrate
php artisan db:seed
```

Seeding creates one login you can test with immediately:

| username | password  |
| -------- | --------- |
| `admin`  | `Passw0rd!` |

Change or remove this in `DatabaseSeeder` before any real deployment.

### Trying it

```bash
php artisan serve
# then:
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Passw0rd!"}'
```

The response includes a bearer token; send it as `Authorization: Bearer <token>`
on subsequent requests. `GET /api/v1/auth/me` confirms who you're logged in as.

## What's in this module

- **Auth** — `POST /v1/auth/login`, `GET /v1/auth/me`, `POST /v1/auth/logout`.
  Stateless Sanctum tokens (no cookies/CSRF needed), so the same API serves the
  web back-office, POS terminals, and a future mobile app identically.
- **User Management** — full CRUD on `users`, plus role assignment
  (`POST /v1/users/{user}/roles`, `DELETE /v1/users/{user}/roles/{userRoleId}`).
  Deactivation instead of deletion (`is_active = false`) — users are referenced
  by history tables (`stock_movements`, `journal_entries`, ...) added in later
  modules, so a hard delete would orphan those records.
- **RBAC** — `roles`, `permissions`, `role_permissions`, `user_roles` match the
  database design doc exactly. A role assignment can be scoped to a branch
  and/or warehouse (`user_roles.branch_id` / `.warehouse_id`); `NULL` means
  "every branch" / "every warehouse". `User::hasPermission()` walks that scope;
  `CheckPermission` middleware enforces it per-route via
  `->middleware('permission:sales.create')`, reading the acting branch/warehouse
  from the `X-Branch-Id` / `X-Warehouse-Id` request headers. `Super Admin` is a
  built-in role that bypasses the check entirely.
- **API structure** — `routes/api.php` groups everything under `/v1`; each
  business module gets its own route block added here the same way. Controllers
  stay thin (auth via Form Request, work delegated to a Service, response
  shaped by an API Resource) — the pattern to keep following for every
  subsequent module (Inventory, Purchasing, Sales, Finance, ...).
- **Validation** — one Form Request per write action (`StoreUserRequest`,
  `UpdateUserRequest`, `AssignRoleRequest`, `StoreRoleRequest`,
  `UpdateRoleRequest`), `authorize()` left permissive since permission checks
  live in route middleware, not scattered across requests.

The permission catalogue (`database/seeders/PermissionSeeder.php`) already
reserves codes for the modules that come next (`inventory.*`, `sales.*`,
`purchasing.manage`, `finance.*`, `reports.view`) so role design doesn't need
revisiting later — only their controllers/services/migrations still need
building.

## Inventory Management module

Tables: `categories`, `manufacturers`, `suppliers`, `products`,
`product_barcodes`, `batches`, `stock`, `stock_movements` — exact schema from
the database design doc.

**Products** carry name, generic/active-ingredient name (`generic_name`),
manufacturer, barcode, price (`purchase_price` / `sale_price`), and a minimum
stock level (`min_stock_level`) plus a reorder point. `GET /v1/products/{id}`
and listings expose a computed `total_stock` (summed live across every batch
of that product, via `Product::stockRecords()`, a `hasManyThrough` to `Stock`
through `Batch` — preloaded with `withSum()` so listings stay a single query)
and `is_below_min_stock`. `GET /v1/products/low-stock` lists everything at or
under its minimum.

**Batches** carry `batch_number`, `expiry_date`, `supplier_id`, and are
received (created or topped up) together with their first quantity in one
call: `POST /v1/batches` (`BatchService::receive()`) — a batch never exists in
this system without a stock movement establishing how much of it there is.

**Stock movements** (`POST /v1/stock-movements/{sale,transfer,customer-return,
supplier-return,damage,expired,adjustment}`) cover Purchase (via batch
receiving), Sale, Transfer, Return (customer and supplier), Damage, Expired,
and Adjustment. Every one is handled by `StockMovementService`, the only place
that writes to `stock` or `stock_movements`.

### The three required business rules, and exactly how each is enforced

1. **Can't sell a quantity that doesn't exist.** `StockMovementService::debit()`
   takes a row lock (`lockForUpdate()`) on the `stock` row and checks
   `quantity_on_hand - reserved_quantity` against the requested amount inside
   the same DB transaction that performs the decrement, so two concurrent
   sales against the same batch/warehouse serialize at the database level
   instead of racing past each other — this is a real concurrency guarantee,
   not just an `if` check. Selling short throws `InsufficientStockException`
   (HTTP 422, `error: "insufficient_stock"`, with `requested` and `available`
   in the body).

2. **Can't sell an expired batch.** The default sale path (no `batch_id`
   given) is FEFO auto-allocation, and its eligible-batches query
   (`sellFefo()`) filters `expiry_date >= today` — an expired batch is never a
   candidate to draw from, by construction. If a specific `batch_id` is
   passed instead (a pharmacist deliberately picking one), it's explicitly
   checked and rejected with `ExpiredBatchException` (422,
   `error: "batch_expired"`). Every *other* movement type (transfer, damage,
   expired write-off, adjustment) is intentionally still allowed to touch an
   expired batch — those are exactly the mechanisms for quarantining or
   writing it off.

3. **Can't delete a posted stock movement.** Three independent layers: (a) no
   `PUT`/`PATCH`/`DELETE` route exists for `/stock-movements` at all; (b)
   `StockMovement::booted()` throws `StockMovementImmutableException` from
   both its `updating` and `deleting` model events, so even direct Eloquent
   code can't do it; (c) the migration has no `updated_at` column in the
   first place. To correct a mistake, record a compensating movement (e.g. an
   `adjustment_in` to undo an over-counted `damage_out`) — never edit history.

### New permissions

`products.view`, `products.manage`, `batches.view`, `batches.manage`,
`inventory.adjust` (joining the existing `inventory.view` /
`inventory.transfer.create`). Granted to Branch Manager (full local
authority), Warehouse Keeper (batches + adjust, since receiving and
cycle-counting is their job), and Cashier (view-only, needed to look products
up at the point of sale); Auditor already inherits every `*.view` permission
automatically, and Super Admin has everything.

## Purchasing module

Tables: `purchase_requests`, `purchase_request_items`, `purchase_orders`,
`purchase_order_items`, `goods_receipts`, `goods_receipt_items`,
`purchase_invoices`, `purchase_invoice_items`, `supplier_payments`,
`supplier_payment_allocations` — plus a minimal Accounting foundation
(`chart_of_accounts`, `journal_entries`, `journal_entry_lines`,
`document_sequences`) that Purchasing posts to and that Sales/Finance will
reuse later.

### The workflow, and exactly how each step is enforced

**Purchase Request → Purchase Order.** A request (`POST /v1/purchase-requests`)
just says what's needed — product + quantity, no supplier or price — and
moves `draft → submitted → approved` (`PurchaseRequestService`). An approved
request is priced for one supplier and turned into a draft
`PurchaseOrder` with `POST /v1/purchase-requests/{id}/convert-to-order`
(`PurchaseOrderService::createFromRequest()`), which also marks the request
`converted`. A `PurchaseOrder` can equally be created directly
(`POST /v1/purchase-orders`) when there was no formal request. Its own status
moves `draft → submitted → approved`; **approval is what unlocks receiving
goods against it** — `GoodsReceiptService::create()` refuses to attach a
receipt to an order that isn't `approved` or already `partially_received`.

**Receive Goods.** `POST /v1/goods-receipts` records what physically arrived
against specific order lines (`draft`, no effect yet) — over-receiving a line
beyond what's still outstanding on the order is rejected
(`OverReceiptException`), checked once optimistically at creation and then
**re-checked with a row lock inside the same transaction that commits it** at
`POST /v1/goods-receipts/{id}/post`, the same discipline
`StockMovementService` uses for stock, so two draft receipts against the same
order line can't both overshoot it. Posting is what makes it real, in one
transaction:
- **Inventory**: for every line, calls `BatchService::receive()` — the exact
  entry point the Inventory module itself uses — so a batch received this way
  is indistinguishable from one received directly through the Inventory API.
- The order line's `received_quantity` advances, and the order's status
  becomes `partially_received` or `received` accordingly.
- **Accounting**: posts one journal entry, **Dr Inventory / Cr GRNI** (Goods
  Received Not Invoiced — a clearing liability for stock that's arrived but
  hasn't been billed yet).

**Invoice.** `POST /v1/purchase-invoices` (optionally linked to the order
and/or receipt) is `draft` until `POST /v1/purchase-invoices/{id}/post`,
which posts **Dr GRNI (or Dr Inventory for a standalone invoice with no
linked receipt) + Dr Purchase Tax Input / Cr Accounts Payable** — this is
what creates the liability that counts toward the supplier's balance.
*Documented simplification*: this assumes the invoice's line prices match
what was booked at receipt; a real deployment with purchase-price variance
would book the difference to a variance account — not implemented here.

**Supplier Balance.** `GET /v1/suppliers/{id}/balance` returns
`total_invoiced` (every posted+ invoice) minus `total_paid`, computed on
demand from the invoices themselves — never stored, so it can't drift.
`POST /v1/supplier-payments` reduces it: pass `allocations` to apply the
payment to specific invoices, or omit it to auto-allocate **FIFO across the
supplier's oldest outstanding invoices first** — the payment equivalent of
Inventory's FEFO sale allocation. Overpaying a specific invoice is rejected
(`OverpaymentException`); every allocation locks its invoice row before
updating `paid_amount`/`status`. A payment posts **Dr Accounts Payable / Cr**
whichever cash/bank account (`chart_of_accounts`) was specified, and is then
append-only, exactly like `StockMovement`/`JournalEntry` — no update/destroy
route, and the model itself throws on any attempt.

### Accounting foundation

`chart_of_accounts` is seeded per company by `AccountingSeeder` with five
accounts the posting logic above depends on: `INVENTORY`, `GRNI`,
`AP` (Accounts Payable), `TAX_INPUT`, `CASH` — see the `CODE_*` constants on
`ChartOfAccount`. `journal_entries`/`journal_entry_lines` are an append-only
double-entry ledger (`JournalEntryService::post()` is the only place that
writes to it, and refuses to post if debits ≠ credits); nothing here writes
directly — every posting module builds a `lines` array and calls it, the same
way every stock change goes through `StockMovementService`.
`document_sequences` is a small reusable counter (`DocumentSequenceService`)
behind every human-readable number in this module (`PO-000001`, `GR-000001`,
`INV-000001`, `PAY-000001`, `JE-000001`), concurrency-safe via
`SELECT ... FOR UPDATE`.

### New permissions

`purchasing.view`, `purchasing.manage`, `purchasing.approve` (separated from
`purchasing.manage` since approval is often a distinct authority from
drafting/receiving) and the existing `finance.journal.view` / `finance.manage`
(now covering the chart of accounts and journal entries too). Branch Manager
gets full authority end-to-end; Warehouse Keeper gets `purchasing.view` +
`purchasing.manage` (sees orders, records what arrives) but not `.approve`,
invoicing, or payments; Cashier is unrelated to Purchasing and gets nothing
here; Auditor inherits every `*.view` permission automatically.

## Sales module

Tables: `customers`, `sales_invoices`, `sales_invoice_items`, `sales_returns`,
`sales_return_items`, `customer_payments`, `customer_payment_allocations` —
plus four new accounts in the shared chart of accounts (`AR`, `REVENUE`,
`COGS`, `TAX_OUTPUT`; see "Accounting foundation" above).

### On sale: exactly how each requirement is enforced

**Stock is debited from the invoice's warehouse.** `POST /v1/sales-invoices`
is `draft` (no effect yet); `POST /v1/sales-invoices/{id}/post` is what
sells, in one transaction. For every line it calls
`StockMovementService::recordSale()` — **the exact same entry point
Inventory's own `/stock-movements/sale` API uses**, not a reimplementation —
so every Inventory guarantee comes along for free:

**Batch is chosen by FEFO.** A line with no `batch_id` auto-allocates across
every non-expired batch of that product in the warehouse, oldest expiry
first, splitting across batches if one alone can't cover the quantity —
`recordSale()`'s existing FEFO path. A line that names a `batch_id` sells
that exact batch, still blocked if it's expired. Selling more than exists is
still impossible for the same reason it's impossible from Inventory directly:
`StockMovementService::debit()` takes a row lock inside the same transaction
that decrements stock. Which batches a line actually drew from is traceable
afterward via `GET /v1/sales-invoices/{id}` → each item's `stock_movements`
(the ledger rows this posting created, linked by
`reference_type = 'sales_invoice_item'`).

**A journal entry is created.** One balanced entry, built from the invoice's
totals and the drawn batches' cost:
- **Dr Accounts Receivable** — `total_amount`
- **Cr Sales Revenue** — `subtotal - discount_amount`
- **Cr Sales Tax Payable** — `tax_amount` (only if nonzero)
- **Dr Cost of Goods Sold / Cr Inventory** — the drawn batches' quantity ×
  each batch's `purchase_price` (its cost at receipt). *Documented
  simplification*: a batch received with no cost on file contributes zero
  COGS for that slice.

**The customer's account is updated.** The invoice counts toward
`GET /v1/customers/{id}/balance` (`total_invoiced`) the moment it posts.
`POST /v1/customer-payments` reduces it — same FIFO-oldest-invoice-first
auto-allocation as Purchasing's supplier payments, or explicit `allocations`;
overpaying a specific invoice is rejected. A payment posts **Dr
<cash/bank account\> / Cr Accounts Receivable** and is append-only from
creation, exactly like `SupplierPayment`.

**Returns** (`POST /v1/sales-returns`, then `.../post`) are the mirror image:
`StockMovementService::recordCustomerReturn()` puts stock back into a
*named* batch (no FEFO — the point is putting it back where it came from),
and the journal entry reverses every line of the sale's (Dr Revenue, Dr Tax
Payable, Cr Receivable, plus Dr Inventory / Cr COGS for the cost). A return
line traced to a specific `sales_invoice_item_id` can't return more than was
actually sold on that line (`OverReturnException`, re-checked with a row
lock at posting — the same discipline as Purchasing's `OverReceiptException`).
*Documented simplification*: a posted return lowers the customer's overall
balance but doesn't retroactively reduce the specific invoice's
`paid_amount`/remaining-due — those still track payments only. A "credit
note" that can be allocated like a payment would be the natural next step.

### New permissions

`sales.view` and `sales.manage` (new — full authority over customers,
invoices and returns), alongside the existing `sales.create` (the narrower
permission already used for a direct stock sale/return via
`/stock-movements`, still relevant for a quick sale with no formal invoice)
and `finance.manage`/`finance.journal.view` (now also covering customer
payments). Branch Manager and Cashier both get `sales.manage` — a cashier
posting an invoice at checkout is exactly what "manage" is for here, same
coarse-grained tradeoff already made for Warehouse Keeper in Purchasing.
Auditor inherits `sales.view`/`finance.journal.view` automatically.

## Accounting module

Tables: `expenses`, `expense_items` — plus a new nullable `category` column
on the existing `chart_of_accounts` (`cash` / `bank` / `receivable` /
`payable`), and two new seeded accounts (`BANK`, `EXPENSE`). Everything else
here — General Ledger, Cash, Banks, Receivables, Payables, P&L, Balance
Sheet — is a **report**, not a new table: all of it is computed on demand
straight from `journal_entry_lines`/`journal_entries` (or, for aging,
straight from the invoices) every time it's requested, the same
never-store-a-balance philosophy already used for Supplier/Customer Balance.

### "اربط القيود تلقائياً مع: Sales, Purchases, Payments, Expenses"

Every one of the four already posts a balanced entry through the same single
write path, `JournalEntryService::post()` (the only place a row is ever
written to `journal_entries`/`journal_entry_lines` — still append-only, still
balance-validated before anything is written):

- **Sales** — `SalesInvoiceService`/`SalesReturnService` (built in the Sales
  module): Dr Receivable / Cr Revenue / Cr Tax Payable / Dr COGS / Cr
  Inventory on posting an invoice, all reversed on a return.
- **Purchases** — `GoodsReceiptService`/`PurchaseInvoiceService` (built in
  the Purchasing module): Dr Inventory / Cr GRNI on receipt, Dr GRNI + Dr Tax
  Input / Cr Accounts Payable on invoicing.
- **Payments** — `SupplierPaymentService`/`CustomerPaymentService` (built in
  the Purchasing/Sales modules): Dr Accounts Payable / Cr Cash-or-Bank, and
  the mirror image for customer payments.
- **Expenses** — `ExpenseService::post()` (new, this module): Dr each
  expense line's account, Cr the `paid_from_account_id` cash/bank account.
  `POST /v1/expenses` creates a `draft` (repeatable `PUT` while draft, one or
  more line items each tagged to its own `type = expense` account — rent,
  utilities, salaries, whatever the chart of accounts defines); `POST
  /v1/expenses/{id}/post` is what writes the journal entry and locks it in
  (`InvalidStatusTransitionException` guards re-posting/editing/cancelling
  afterward, same draft→posted→cancelled pattern as every other document in
  this system). *Documented simplification*: there's no "accrued expense" —
  an expense is always paid immediately from a named cash/bank account, so
  there's no Accounts Payable line for it and no separate "pay this expense
  later" step.

### General Ledger

`GET /v1/accounting/general-ledger?company_id=&as_of_date=` — the trial
balance: every account's total debit, total credit and net balance
(debit-normal for `asset`/`expense`, credit-normal for
`liability`/`equity`/`revenue`) as of a date, defaulting to all-time.
`GET /v1/accounting/general-ledger/{chartOfAccount}?date_from=&date_to=` —
one account's full posting history with a running balance, opening balance
computed from everything before `date_from`.

### Cash, Banks, Receivables, Payables

`GET /v1/accounting/cash` and `GET /v1/accounting/banks` are the same trial
balance, filtered to accounts tagged `category = cash` / `category = bank`
— a company can have several bank accounts (each one its own row with its
own balance; `ChartOfAccount::forCategory()` is the lookup). `GET
/v1/accounting/receivables` and `GET /v1/accounting/payables` are aging
reports (`AgingReportService`): every posted/partially-paid invoice still
owing money, bucketed by days overdue from its `due_date` (falling back to
`invoice_date`) into `current` / `1_30` / `31_60` / `61_90` / `90_plus`,
grouped by customer/supplier — reading the same `remaining_due`
(`total_amount - paid_amount`) that `/customers/{id}/balance` and
`/suppliers/{id}/balance` already expose, never a separately tracked figure.

### Profit & Loss and Balance Sheet

`GET /v1/accounting/profit-and-loss?company_id=&date_from=&date_to=` sums
every `revenue`/`expense` account's net movement in the date range and
nets them into `net_profit`. `GET
/v1/accounting/balance-sheet?company_id=&as_of_date=` sums every
`asset`/`liability`/`equity` account's balance as of the date.
*Documented simplification*: the system has no period-closing process, so
there's no stored Retained Earnings balance for the sheet to read — instead
it adds a synthetic **"Current Earnings"** line to equity, equal to
all-time revenue minus all-time expense as of that date (i.e. exactly what
`profitAndLoss()` with no `date_from` would return), which is what keeps
`is_balanced` (`Assets == Liabilities + Equity`) true without a close. A
real close would replace this with a permanent Retained Earnings balance
plus a P&L scoped to the current period only.

### New permissions

None — every route above reuses the existing `finance.journal.view` (reads)
and `finance.manage` (writes: chart-of-accounts, manual journal entries,
expenses) permissions, already held by Accountant/Admin/Auditor from the
Purchasing module's RBAC seeding.

## Branch & Warehouse management, and frontend-support additions

Added while building the companion Next.js frontend, which needed a
"Warehouses" page and a reliable `company_id` on the authenticated user —
neither existed yet:

- **`GET /v1/branches`, `GET /v1/branches/{branch}`** — read-only; branches
  are seeded per company, not created through the API.
- **`GET /v1/warehouses`, `POST /v1/warehouses`, `GET
  /v1/warehouses/{warehouse}`, `PUT /v1/warehouses/{warehouse}`** — full CRUD
  except `destroy()`: `stock`, `batches` and `stock_movements` all reference
  a warehouse, so it's deactivated (`is_active = false`) instead of deleted.
  `type` is one of `main | sub | quarantine | returns`; `code` is unique per
  branch. Both routes are gated by `branches.manage` — a permission code that
  was already reserved in `PermissionSeeder` from day one but had nothing
  wired to it until now.
- **`UserResource`** now includes `company_id` (derived from the loaded
  `branch` relation — a user only ever belongs to one company via their
  branch) and, on each entry in `roles[]`, `user_role_id` — the
  `user_roles` pivot row's own id, which `DELETE
  /v1/users/{user}/roles/{userRoleId}` needs (the same role can be assigned
  to a user more than once at different branch/warehouse scopes, so the role
  id alone isn't enough to identify which assignment to revoke).

### New permissions

None — `branches.manage` already existed in the seeded catalogue; this is
the first release that actually checks it on a route.

## Sales reports

Added alongside the frontend's Reports page, which needed daily/monthly/
by-branch sales breakdowns that no endpoint produced yet — `GET
/v1/sales-invoices` lists invoices but doesn't aggregate them.
`SalesReportService` computes all three on demand from `sales_invoices`
(same "never store a report" approach as the Accounting reports), counting
only `posted`, `partially_paid` and `paid` invoices as real sales — `draft`
has no stock/accounting effect yet, and `cancelled` is excluded from the
customer's balance, so both are left out of every figure here too:

- **`GET /v1/reports/sales/daily?company_id=&date=`** — every real-sale
  invoice on one date, plus the day's `invoice_count`, `total_subtotal`,
  `total_discount`, `total_tax` and `total_revenue`. `date` defaults to today.
- **`GET /v1/reports/sales/monthly?company_id=&year=&month=`** — a
  day-by-day breakdown (`invoice_count`, `total_revenue` per day) for one
  calendar month, plus the month's totals. `year`/`month` default to the
  current month.
- **`GET /v1/reports/sales/by-branch?company_id=&date_from=&date_to=`** —
  `invoice_count`/`total_revenue` grouped by branch over an optional date
  range (either end may be omitted for an open range), sorted by revenue
  descending.

`GET /v1/sales-invoices` also gained `branch_id`, `date_from` and `date_to`
query filters in this pass, for the same reason `/v1/journal-entries` and
`/v1/expenses` already had them — general-purpose filtering that was simply
missing before.

### New permissions

None — all three routes reuse the existing `sales.view` permission.

## Next modules (build in this order, per the development plan)

1. Audit log — `audit_logs`, wired as a model observer across every module
   (Purchasing, Sales and Accounting already give it a lot to observe: every
   status transition and every journal entry)
2. Reporting — cross-module operational reports (the core *financial*
   reports — General Ledger, Cash, Banks, Receivables/Payables aging, P&L,
   Balance Sheet — are already covered by the Accounting module above; this
   is for everything else, e.g. inventory valuation, expiry/near-expiry
   listings, sales-by-product, dashboards)
