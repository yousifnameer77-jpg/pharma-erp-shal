// Shared API types. Money/quantity fields the backend casts as `decimal:N`
// are serialized as JSON strings (e.g. "total_amount": "1050.000") — those
// fields are typed `string` here and formatted/parsed with lib/format.ts.
// Computed accessor fields (total_stock, remaining_due, balance, etc.) come
// back as real numbers and are typed `number`.

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  path: string;
  per_page: number;
  to: number | null;
  total: number;
}

export interface PaginationLinks {
  first: string | null;
  last: string | null;
  prev: string | null;
  next: string | null;
}

export interface Paginated<T> {
  data: T[];
  links: PaginationLinks;
  meta: PaginationMeta;
}

export interface RoleScope {
  branch_id: string | null;
  warehouse_id: string | null;
}

export interface UserRoleSummary {
  id: string;
  /** The user_roles pivot row id — required by DELETE /users/{id}/roles/{userRoleId}, not `id` (the role id). */
  user_role_id: string;
  name: string;
  scope: RoleScope;
  permissions: string[] | null;
}

export interface User {
  id: string;
  username: string;
  email: string | null;
  full_name: string;
  phone: string | null;
  is_active: boolean;
  company_id: string | null;
  branch: { id: string; name: string } | null;
  roles?: UserRoleSummary[];
  last_login_at: string | null;
  created_at: string;
}

export interface Permission {
  id: string;
  code: string;
  module: string;
  description: string;
}

export interface Role {
  id: string;
  name: string;
  description: string | null;
  is_system_role: boolean;
  permissions?: Permission[];
  created_at: string;
}

export interface UserRoleAssignment {
  id: string;
  user_id: string;
  role_id: string;
  branch_id: string | null;
  warehouse_id: string | null;
  created_at: string;
  updated_at: string;
  role?: Role;
  branch?: { id: string; name: string } | null;
  warehouse?: { id: string; name: string } | null;
}

export interface Branch {
  id: string;
  company_id: string;
  name: string;
  code: string;
  address: string | null;
  phone: string | null;
  manager_user_id: string | null;
  is_active: boolean;
  warehouses_count?: number;
}

export type WarehouseType = "main" | "sub" | "quarantine" | "returns";

export interface Warehouse {
  id: string;
  branch_id: string;
  name: string;
  code: string;
  type: WarehouseType;
  location: string | null;
  is_active: boolean;
  branch?: { id: string; name: string } | null;
  created_at: string;
}

export interface Category {
  id: string;
  parent_id: string | null;
  name: string;
  description: string | null;
  parent?: { id: string; name: string } | null;
  products_count?: number;
}

export interface Manufacturer {
  id: string;
  name: string;
  country: string | null;
  contact_info: Record<string, unknown> | null;
  is_active: boolean;
  products_count?: number;
}

export interface Supplier {
  id: string;
  company_id: string;
  name: string;
  contact_person: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  tax_number: string | null;
  payment_terms: string | null;
  is_active: boolean;
}

export interface SupplierBalance {
  total_invoiced: number;
  total_paid: number;
  balance: number;
}

export interface Product {
  id: string;
  category_id: string | null;
  manufacturer_id: string;
  code: string;
  barcode: string | null;
  name: string;
  generic_name: string | null;
  form: string | null;
  strength: string | null;
  base_unit: string;
  pack_size: number | null;
  is_controlled_substance: boolean;
  requires_prescription: boolean;
  min_stock_level: string | null;
  reorder_point: string | null;
  purchase_price: string | null;
  sale_price: string | null;
  tax_rate: string | null;
  is_active: boolean;
  category: { id: string; name: string } | null;
  manufacturer: { id: string; name: string } | null;
  total_stock: number;
  is_below_min_stock: boolean;
  created_at: string;
  updated_at: string;
}

export interface Batch {
  id: string;
  product_id: string;
  batch_number: string;
  manufacture_date: string | null;
  expiry_date: string;
  supplier_id: string | null;
  purchase_price: string | null;
  is_expired: boolean;
  total_quantity: number;
  product?: { id: string; name: string; base_unit: string } | null;
  supplier?: { id: string; name: string } | null;
  created_at: string;
}

export interface StockRow {
  warehouse_id: string;
  batch_id: string;
  quantity_on_hand: string;
  reserved_quantity: string;
  available_quantity: number;
  warehouse?: { id: string; name: string } | null;
  batch?: {
    id: string;
    batch_number: string;
    expiry_date: string;
    is_expired: boolean;
    product: { id: string; name: string } | null;
  } | null;
  updated_at: string;
}

export type PurchaseRequestStatus =
  | "draft"
  | "submitted"
  | "approved"
  | "rejected"
  | "converted"
  | "cancelled";

export interface PurchaseRequestItem {
  id: string;
  product_id: string;
  product?: { id: string; name: string } | null;
  quantity: string;
  notes: string | null;
}

export interface PurchaseRequest {
  id: string;
  company_id: string;
  branch_id: string;
  warehouse_id: string;
  request_number: string;
  status: PurchaseRequestStatus;
  requested_by: string | null;
  approved_by: string | null;
  notes: string | null;
  items?: PurchaseRequestItem[];
  created_at: string;
  updated_at: string;
}

export type PurchaseOrderStatus =
  | "draft"
  | "submitted"
  | "approved"
  | "partially_received"
  | "received"
  | "cancelled"
  | "closed";

export interface PurchaseOrderItem {
  id: string;
  product_id: string;
  product?: { id: string; name: string } | null;
  quantity: string;
  unit_price: string;
  tax_rate: string | null;
  received_quantity: string;
  remaining_quantity: number;
  line_total: number;
  notes: string | null;
}

export interface PurchaseOrder {
  id: string;
  company_id: string;
  branch_id: string;
  warehouse_id: string;
  supplier_id: string;
  purchase_request_id: string | null;
  order_number: string;
  status: PurchaseOrderStatus;
  order_date: string;
  expected_date: string | null;
  notes: string | null;
  created_by: string | null;
  approved_by: string | null;
  supplier?: { id: string; name: string } | null;
  items?: PurchaseOrderItem[];
  created_at: string;
  updated_at: string;
}

export type GoodsReceiptStatus = "draft" | "posted" | "cancelled";

export interface GoodsReceiptItem {
  id: string;
  purchase_order_item_id: string;
  product_id: string;
  product?: { id: string; name: string } | null;
  batch_number: string;
  manufacture_date: string | null;
  expiry_date: string;
  quantity: string;
  unit_cost: string;
}

export interface GoodsReceipt {
  id: string;
  company_id: string;
  warehouse_id: string;
  purchase_order_id: string;
  receipt_number: string;
  receipt_date: string;
  status: GoodsReceiptStatus;
  received_by: string | null;
  notes: string | null;
  posted_at: string | null;
  purchase_order?: { id: string; order_number: string } | null;
  items?: GoodsReceiptItem[];
  created_at: string;
}

export type PurchaseInvoiceStatus =
  | "draft"
  | "posted"
  | "partially_paid"
  | "paid"
  | "cancelled";

export interface PurchaseInvoiceItem {
  id: string;
  product_id: string;
  product?: { id: string; name: string } | null;
  quantity: string;
  unit_price: string;
  tax_rate: string | null;
  line_total: string;
}

export interface PurchaseInvoice {
  id: string;
  company_id: string;
  supplier_id: string;
  purchase_order_id: string | null;
  goods_receipt_id: string | null;
  invoice_number: string;
  supplier_invoice_number: string | null;
  invoice_date: string;
  due_date: string | null;
  status: PurchaseInvoiceStatus;
  subtotal: string;
  tax_amount: string;
  total_amount: string;
  paid_amount: string;
  remaining_due: number;
  notes: string | null;
  supplier?: { id: string; name: string } | null;
  items?: PurchaseInvoiceItem[];
  posted_at: string | null;
  created_at: string;
}

export type PaymentMethod =
  | "cash"
  | "bank_transfer"
  | "cheque"
  | "card"
  | "other";

export interface SupplierPayment {
  id: string;
  company_id: string;
  supplier_id: string;
  payment_number: string;
  payment_date: string;
  amount: string;
  method: PaymentMethod;
  paid_from_account_id: string;
  reference: string | null;
  notes: string | null;
  allocations?: { purchase_invoice_id: string; amount: string }[];
  created_at: string;
}

export interface Customer {
  id: string;
  company_id: string;
  name: string;
  contact_person: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  tax_number: string | null;
  credit_limit: string | null;
  payment_terms: string | null;
  is_active: boolean;
}

export interface CustomerBalance {
  total_invoiced: number;
  total_returned: number;
  total_paid: number;
  balance: number;
}

export type SalesInvoiceStatus =
  | "draft"
  | "posted"
  | "partially_paid"
  | "paid"
  | "cancelled";

export interface SalesInvoiceItem {
  id: string;
  product_id: string;
  product?: { id: string; name: string } | null;
  batch_id: string | null;
  quantity: string;
  unit_price: string;
  tax_rate: string | null;
  discount_rate: string | null;
  line_total: string;
  returned_quantity: string;
  remaining_returnable_quantity: number;
  stock_movements?: { batch_id: string; quantity: string }[] | null;
}

export interface SalesInvoice {
  id: string;
  company_id: string;
  branch_id: string;
  warehouse_id: string;
  customer_id: string;
  invoice_number: string;
  invoice_date: string;
  due_date: string | null;
  status: SalesInvoiceStatus;
  subtotal: string;
  discount_amount: string;
  tax_amount: string;
  total_amount: string;
  paid_amount: string;
  remaining_due: number;
  notes: string | null;
  customer?: { id: string; name: string } | null;
  items?: SalesInvoiceItem[];
  posted_at: string | null;
  created_at: string;
}

export interface DailySalesReport {
  date: string;
  invoices: SalesInvoice[];
  invoice_count: number;
  total_subtotal: number;
  total_discount: number;
  total_tax: number;
  total_revenue: number;
}

export interface MonthlySalesDay {
  date: string;
  invoice_count: number;
  total_revenue: number;
}

export interface MonthlySalesReport {
  year: number;
  month: number;
  days: MonthlySalesDay[];
  invoice_count: number;
  total_revenue: number;
}

export interface SalesByBranchRow {
  branch: { id: string; name: string } | null;
  invoice_count: number;
  total_revenue: number;
}

export interface SalesByBranchReport {
  date_from: string | null;
  date_to: string | null;
  branches: SalesByBranchRow[];
  invoice_count: number;
  total_revenue: number;
}

export type SalesReturnStatus = "draft" | "posted" | "cancelled";

export interface SalesReturnItem {
  id: string;
  sales_invoice_item_id: string | null;
  product_id: string;
  product?: { id: string; name: string } | null;
  batch_id: string;
  quantity: string;
  unit_price: string;
  tax_rate: string | null;
  line_total: string;
}

export interface SalesReturn {
  id: string;
  company_id: string;
  warehouse_id: string;
  customer_id: string;
  sales_invoice_id: string | null;
  return_number: string;
  return_date: string;
  status: SalesReturnStatus;
  subtotal: string;
  tax_amount: string;
  total_amount: string;
  notes: string | null;
  customer?: { id: string; name: string } | null;
  items?: SalesReturnItem[];
  posted_at: string | null;
  created_at: string;
}

export interface CustomerPayment {
  id: string;
  company_id: string;
  customer_id: string;
  payment_number: string;
  payment_date: string;
  amount: string;
  method: PaymentMethod;
  received_into_account_id: string;
  reference: string | null;
  notes: string | null;
  allocations?: { sales_invoice_id: string; amount: string }[];
  created_at: string;
}

export type AccountType =
  | "asset"
  | "liability"
  | "equity"
  | "revenue"
  | "expense";
export type AccountCategory = "cash" | "bank" | "receivable" | "payable" | null;

export interface ChartOfAccount {
  id: string;
  company_id: string;
  code: string;
  name: string;
  type: AccountType;
  category: AccountCategory;
  parent_id: string | null;
  is_active: boolean;
}

export interface JournalEntryLine {
  id: string;
  account_id: string;
  account?: { code: string; name: string } | null;
  debit: string;
  credit: string;
  description: string | null;
}

export interface JournalEntry {
  id: string;
  entry_number: string;
  entry_date: string;
  reference_type: string | null;
  reference_id: string | null;
  description: string;
  created_by: string | null;
  lines?: JournalEntryLine[];
  created_at: string;
}

export type ExpenseStatus = "draft" | "posted" | "cancelled";

export interface ExpenseItem {
  id: string;
  account_id: string;
  account?: { code: string; name: string } | null;
  amount: string;
  description: string | null;
}

export interface Expense {
  id: string;
  company_id: string;
  branch_id: string | null;
  expense_number: string;
  expense_date: string;
  paid_from_account_id: string;
  total_amount: string;
  status: ExpenseStatus;
  notes: string | null;
  items?: ExpenseItem[];
  posted_at: string | null;
  created_at: string;
}

export interface TrialBalanceRow {
  account: ChartOfAccount;
  total_debit: number;
  total_credit: number;
  balance: number;
}

export interface AccountLedger {
  account: ChartOfAccount;
  opening_balance: number;
  closing_balance: number;
  transactions: {
    date: string;
    entry_number: string;
    description: string | null;
    debit: number;
    credit: number;
    running_balance: number;
  }[];
}

export interface AgingBuckets {
  current: number;
  "1_30": number;
  "31_60": number;
  "61_90": number;
  "90_plus": number;
}

export interface ReceivablesAging {
  as_of_date: string;
  customers: { customer: Customer; buckets: AgingBuckets; total: number }[];
  totals: AgingBuckets;
  grand_total: number;
}

export interface PayablesAging {
  as_of_date: string;
  suppliers: { supplier: Supplier; buckets: AgingBuckets; total: number }[];
  totals: AgingBuckets;
  grand_total: number;
}

export interface ProfitAndLoss {
  date_from: string | null;
  date_to: string | null;
  revenue: { account: ChartOfAccount; amount: number }[];
  total_revenue: number;
  expenses: { account: ChartOfAccount; amount: number }[];
  total_expense: number;
  net_profit: number;
}

export interface BalanceSheet {
  as_of_date: string | null;
  assets: { account: ChartOfAccount; amount: number }[];
  total_assets: number;
  liabilities: { account: ChartOfAccount; amount: number }[];
  total_liabilities: number;
  equity: { account: ChartOfAccount; amount: number }[];
  current_earnings: number;
  total_equity: number;
  is_balanced: boolean;
}
