# Pharma ERP — Frontend

A professional, responsive enterprise UI for the Pharma ERP backend, built with **Next.js 14 (App Router)**, **React 18**, **TypeScript** and **Tailwind CSS**. No third-party UI kit — every component (buttons, tables, modals, drawers, tabs, forms) is hand-built and shared across all 11 pages for a consistent, clean enterprise look.

## Pages

| Page | Route | Notes |
|---|---|---|
| Login | `/login` | Sanctum bearer-token auth |
| Dashboard | `/dashboard` | KPIs, monthly P&L chart, recent sales/purchases — sections hidden per permission |
| Products | `/products` | Products, Categories, Manufacturers |
| Warehouses | `/warehouses` | Branch-scoped storage locations (main/sub/quarantine/returns) |
| Purchases | `/purchases` | Requests → Orders → Goods Receipts → Invoices → Supplier Payments |
| Sales | `/sales` | Invoices (FEFO batch auto-allocation) → Returns → Customer Payments |
| Customers / Suppliers | `/customers`, `/suppliers` | CRUD + running balance |
| Accounting | `/accounting` | Chart of Accounts, Journal Entries, Expenses, General Ledger, Cash & Banks, Receivables/Payables aging, Profit & Loss, Balance Sheet |
| Reports | `/reports` | Inventory, Sales and Finance report categories (see below) — every report table exports to Excel/PDF or prints |
| Users | `/users` | Staff accounts, role assignment, and the permission catalogue (Roles tab) |

Every page and every section within a page is gated by the signed-in user's permissions (mirroring the backend's `permission:*` route middleware) — a `Super Admin` role bypasses every gate, matching the backend.

### Reports (`/reports`)

Three categories, each its own set of tabs, every report table with **Excel**, **PDF** and **Print** buttons (`src/components/reports/export-bar.tsx`):

- **Inventory** (`src/components/reports/inventory/`) — Stock Balance (per warehouse, on hand/reserved/available), Expiring Products (7/30/60/90-day window), Expired Products, Batch Report (every batch, filterable by product/supplier).
- **Sales** (`src/components/reports/sales/`) — Daily Sales (one date, invoice list + totals), Monthly Sales (day-by-day trend chart + table for a chosen month), Sales by Branch (revenue compared across branches over a date range). Backed by three new read-only endpoints (`GET /v1/reports/sales/daily|monthly|by-branch`) added to the backend for this — see its README.
- **Finance** (`src/components/reports/finance/`) — Customer Debt and Supplier Debt (receivables/payables aging, reused from the Accounting module), Profit & Loss (revenue/expense breakdown with a combined exportable table).

## Getting started

```bash
npm install
cp .env.local.example .env.local   # set NEXT_PUBLIC_API_URL to your backend
npm run dev
```

The app expects the Laravel backend's API at `NEXT_PUBLIC_API_URL` (default `http://localhost:8000/api`), and calls `${NEXT_PUBLIC_API_URL}/v1/...` for every request.

**Demo login** (seeded by the backend's `DatabaseSeeder`): username `admin`, password `Passw0rd!`.

## Architecture notes

- **Auth**: Sanctum bearer token in `localStorage`, attached by `src/lib/api.ts`. The active branch/warehouse scope (used for `X-Branch-Id` / `X-Warehouse-Id` headers the backend's `CheckPermission` middleware reads) is derived from the signed-in user's own branch on login and kept in `localStorage` alongside the token — there is no manual scope switcher in this build.
- **Data fetching**: three small hooks in `src/lib/hooks.ts` cover every shape the backend returns — `usePaginatedResource` (Laravel `->paginate()`), `useSimpleList` (a plain `{data: [...]}` list), and `useApiResource` (a single `{data: {...}}` resource, including read-only reports). No React Query — deliberately kept dependency-light.
- **Money/quantity fields**: the backend serializes every `decimal:N`-cast column as a JSON *string* (e.g. `"total_amount": "1050.000"`) so PHP never loses precision on the wire. `src/lib/types.ts` types those fields `string` and `src/lib/format.ts` (`toNumber`, `formatMoney`, `formatNumber`) converts them back for display and computation. Computed accessor fields (`total_stock`, `remaining_due`, `balance`, `is_balanced`, …) come back as real numbers/booleans and are typed accordingly.
- **Forms**: `EntityFormModal` (`src/components/forms/entity-form-modal.tsx`) is a schema-driven form builder used for simple flat-field CRUD (Categories, Manufacturers, Warehouses, Customers, Suppliers, Products, Chart of Accounts). `ItemsEditor` (`src/components/forms/items-editor.tsx`) is a generic line-items table used by every document form (purchase/sales lines, journal entry lines, expense items). Workflow logic (status guards, post/cancel actions, payment allocation) is written explicitly per page rather than over-abstracted.
- **FEFO**: Sales Invoice creation deliberately has no batch picker — the backend auto-allocates first-expiry-first-out on posting. Sales Returns require an explicit batch, so the form offers "load lines from invoice" or a manual product → batch cascading select.
- **RBAC**: `src/lib/permissions.ts` mirrors the backend's seeded permission codes exactly. `src/lib/auth-context.tsx` exposes `hasPermission()`; the sidebar (`nav-config.ts`) and every page/section check it before rendering.
- **Report export**: `src/lib/export.ts` wraps `xlsx` (Excel) and `jspdf` + `jspdf-autotable` (PDF), both client-side — no server round-trip, so exports work offline once the report data has loaded. Every report tab builds a small `ExportColumn<T>[]` (a header + a plain-string value getter) alongside its on-screen `<DataTable>` columns and hands both to `<ReportExportBar>`, which renders the Excel/PDF/Print buttons.

## Sandbox limitation

This project was authored in a sandbox with no access to the npm registry (`npm install` returns `403 Forbidden`), so it was **not** possible to run `next build`/`next dev` or install dependencies while building it. Every `.ts`/`.tsx` file was instead validated with `prettier --check` (which bundles its own TypeScript/JSX parser and needs no `node_modules`) after every batch of changes, and the dependency versions in `package.json` were chosen deliberately conservative and mutually compatible (Next 14.2 + React 18.3, not React 19) to minimize the risk of a version mismatch on a real `npm install`.

Before running this for real, a full `npm install && npm run build` is recommended as a first smoke test in an environment with registry access.

## Tech stack

Next.js 14 (App Router) · React 18 · TypeScript · Tailwind CSS 3 · lucide-react (icons) · recharts (charts) · xlsx (Excel export) · jspdf + jspdf-autotable (PDF export)
