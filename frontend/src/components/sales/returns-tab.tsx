"use client";

import { useMemo, useState } from "react";
import { Plus } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import {
  usePaginatedResource,
  useSimpleList,
  useApiResource,
} from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { formatDate, formatMoney, todayIso } from "@/lib/format";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Pagination } from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";
import { Select, Input } from "@/components/ui/field";
import { StatusBadge } from "@/components/ui/badge";
import { Modal } from "@/components/ui/modal";
import { Drawer, DetailRow, DetailSection } from "@/components/ui/drawer";
import {
  ItemsEditor,
  inlineInputClasses,
} from "@/components/forms/items-editor";
import { useToast } from "@/components/ui/toast";
import type {
  Batch,
  Customer,
  Product,
  SalesInvoice,
  SalesReturn,
  Warehouse,
} from "@/lib/types";

interface DraftItem {
  sales_invoice_item_id: string;
  product_id: string;
  batch_id: string;
  quantity: string;
  unit_price: string;
  tax_rate: string;
}

function emptyItem(): DraftItem {
  return {
    sales_invoice_item_id: "",
    product_id: "",
    batch_id: "",
    quantity: "1",
    unit_price: "0",
    tax_rate: "0",
  };
}

export function SalesReturnsTab({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.salesManage);
  const toast = useToast();

  const [creating, setCreating] = useState(false);
  const [selected, setSelected] = useState<SalesReturn | null>(null);
  const [busy, setBusy] = useState(false);

  const list = usePaginatedResource<SalesReturn>("/v1/sales-returns", {
    company_id: companyId,
  });

  async function post(ret: SalesReturn) {
    if (
      !confirm(
        "Post this return? Stock will be restocked and the customer balance updated.",
      )
    )
      return;
    setBusy(true);
    try {
      await api.post(`/v1/sales-returns/${ret.id}/post`);
      toast.success("Return posted.");
      list.refetch();
      setSelected(null);
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "Could not post this return.",
      );
    } finally {
      setBusy(false);
    }
  }

  const columns: Column<SalesReturn>[] = [
    {
      key: "number",
      header: "Return #",
      render: (r) => (
        <span className="font-medium text-slate-900">{r.return_number}</span>
      ),
    },
    {
      key: "customer",
      header: "Customer",
      render: (r) => r.customer?.name ?? "—",
    },
    { key: "date", header: "Date", render: (r) => formatDate(r.return_date) },
    {
      key: "total",
      header: "Total",
      render: (r) => formatMoney(r.total_amount),
      className: "text-right",
    },
    {
      key: "status",
      header: "Status",
      render: (r) => <StatusBadge status={r.status} />,
    },
  ];

  return (
    <Card>
      <div className="flex items-center justify-end border-b border-slate-100 p-4">
        {canManage && (
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" /> New return
          </Button>
        )}
      </div>
      <DataTable
        columns={columns}
        rows={list.data}
        rowKey={(r) => r.id}
        loading={list.loading}
        error={list.error}
        onRowClick={(r) => setSelected(r)}
        emptyTitle="No sales returns yet"
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />

      {creating && (
        <ReturnFormModal
          companyId={companyId}
          onClose={() => setCreating(false)}
          onDone={() => {
            setCreating(false);
            list.refetch();
          }}
        />
      )}

      {selected && (
        <Drawer
          open
          onClose={() => setSelected(null)}
          title={selected.return_number}
          subtitle={<StatusBadge status={selected.status} />}
        >
          <DetailSection title="Details">
            <DetailRow
              label="Customer"
              value={selected.customer?.name ?? "—"}
            />
            <DetailRow
              label="Return date"
              value={formatDate(selected.return_date)}
            />
          </DetailSection>
          <DetailSection title="Items">
            <div className="space-y-1">
              {selected.items?.map((item) => (
                <div
                  key={item.id}
                  className="flex items-center justify-between py-1 text-sm"
                >
                  <span>
                    {item.product?.name ?? item.product_id}{" "}
                    <span className="text-slate-400">× {item.quantity}</span>
                  </span>
                  <span className="font-medium">
                    {formatMoney(item.line_total)}
                  </span>
                </div>
              ))}
            </div>
          </DetailSection>
          {canManage && selected.status === "draft" && (
            <div className="flex flex-wrap gap-2 pt-2">
              <Button size="sm" loading={busy} onClick={() => post(selected)}>
                Post
              </Button>
              <Button
                size="sm"
                variant="outline"
                loading={busy}
                onClick={async () => {
                  if (!confirm("Cancel this return?")) return;
                  setBusy(true);
                  try {
                    await api.post(`/v1/sales-returns/${selected.id}/cancel`);
                    toast.success("Return cancelled.");
                    list.refetch();
                    setSelected(null);
                  } catch (err) {
                    toast.error(
                      err instanceof ApiError
                        ? err.summary
                        : "Could not cancel this return.",
                    );
                  } finally {
                    setBusy(false);
                  }
                }}
              >
                Cancel
              </Button>
            </div>
          )}
        </Drawer>
      )}
    </Card>
  );
}

function ReturnFormModal({
  companyId,
  onClose,
  onDone,
}: {
  companyId: string;
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const [customerId, setCustomerId] = useState("");
  const [warehouseId, setWarehouseId] = useState("");
  const [invoiceId, setInvoiceId] = useState("");
  const [returnDate, setReturnDate] = useState(todayIso());
  const [items, setItems] = useState<DraftItem[]>([emptyItem()]);
  const [busy, setBusy] = useState(false);
  const { user } = useAuth();

  const customers = useSimpleList<Customer>("/v1/customers", {
    company_id: companyId,
    per_page: 500,
  });
  const products = useSimpleList<Product>("/v1/products", { per_page: 500 });
  const warehouses = useSimpleList<Warehouse>("/v1/warehouses", {
    branch_id: user?.branch?.id,
  });
  const batches = useSimpleList<Batch>("/v1/batches", { per_page: 500 });
  const invoices = usePaginatedResource<SalesInvoice>("/v1/sales-invoices", {
    company_id: companyId,
    customer_id: customerId || undefined,
    per_page: 200,
  });
  const invoiceDetail = useApiResource<SalesInvoice>(
    invoiceId ? `/v1/sales-invoices/${invoiceId}` : null,
  );
  const postedInvoices = useMemo(
    () =>
      invoices.data.filter(
        (i) => i.status !== "draft" && i.status !== "cancelled",
      ),
    [invoices.data],
  );

  function loadFromInvoice() {
    if (!invoiceDetail.data) return;
    const rows = (invoiceDetail.data.items ?? [])
      .filter((i) => i.remaining_returnable_quantity > 0)
      .map((i) => ({
        sales_invoice_item_id: i.id,
        product_id: i.product_id,
        batch_id: i.batch_id ?? i.stock_movements?.[0]?.batch_id ?? "",
        quantity: String(i.remaining_returnable_quantity),
        unit_price: i.unit_price,
        tax_rate: i.tax_rate ?? "0",
      }));
    setItems(rows.length > 0 ? rows : [emptyItem()]);
  }

  function batchesForProduct(productId: string) {
    return batches.data.filter((b) => b.product_id === productId);
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await api.post("/v1/sales-returns", {
        company_id: companyId,
        warehouse_id: warehouseId,
        customer_id: customerId,
        sales_invoice_id: invoiceId || undefined,
        return_date: returnDate,
        items: items
          .filter((i) => i.product_id && i.batch_id)
          .map((i) => ({
            sales_invoice_item_id: i.sales_invoice_item_id || undefined,
            product_id: i.product_id,
            batch_id: i.batch_id,
            quantity: Number(i.quantity),
            unit_price: Number(i.unit_price),
            tax_rate: i.tax_rate ? Number(i.tax_rate) : undefined,
          })),
      });
      toast.success("Sales return created as a draft.");
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "Could not create this return.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title="New sales return"
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button onClick={submit} loading={busy}>
            Create return
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Select
          label="Customer"
          required
          value={customerId}
          onChange={(e) => {
            setCustomerId(e.target.value);
            setInvoiceId("");
          }}
          placeholder="Select customer"
        >
          {customers.data.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </Select>
        <Select
          label="Warehouse"
          required
          value={warehouseId}
          onChange={(e) => setWarehouseId(e.target.value)}
          placeholder="Select warehouse"
        >
          {warehouses.data.map((w) => (
            <option key={w.id} value={w.id}>
              {w.name}
            </option>
          ))}
        </Select>
        <Input
          label="Return date"
          type="date"
          required
          value={returnDate}
          onChange={(e) => setReturnDate(e.target.value)}
        />
        <div className="flex items-end gap-2">
          <Select
            label="Against invoice (optional)"
            value={invoiceId}
            onChange={(e) => setInvoiceId(e.target.value)}
            placeholder="Manual entry"
            wrapClassName="flex-1"
          >
            {postedInvoices.map((i) => (
              <option key={i.id} value={i.id}>
                {i.invoice_number}
              </option>
            ))}
          </Select>
          {invoiceId && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={loadFromInvoice}
              disabled={invoiceDetail.loading}
            >
              Load lines
            </Button>
          )}
        </div>

        <ItemsEditor<DraftItem>
          items={items}
          onChange={setItems}
          newItem={emptyItem}
          columns={[
            {
              key: "product",
              header: "Product",
              width: "28%",
              render: (item, update) => (
                <select
                  className={inlineInputClasses}
                  value={item.product_id}
                  onChange={(e) =>
                    update({ product_id: e.target.value, batch_id: "" })
                  }
                >
                  <option value="">Select…</option>
                  {products.data.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
                </select>
              ),
            },
            {
              key: "batch",
              header: "Batch",
              width: "22%",
              render: (item, update) => (
                <select
                  className={inlineInputClasses}
                  value={item.batch_id}
                  onChange={(e) => update({ batch_id: e.target.value })}
                >
                  <option value="">Select batch…</option>
                  {batchesForProduct(item.product_id).map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.batch_number}
                    </option>
                  ))}
                </select>
              ),
            },
            {
              key: "quantity",
              header: "Qty",
              width: "14%",
              render: (item, update) => (
                <input
                  type="number"
                  min={0.001}
                  step="0.001"
                  className={inlineInputClasses}
                  value={item.quantity}
                  onChange={(e) => update({ quantity: e.target.value })}
                />
              ),
            },
            {
              key: "unit_price",
              header: "Unit price",
              width: "18%",
              render: (item, update) => (
                <input
                  type="number"
                  min={0}
                  step="0.001"
                  className={inlineInputClasses}
                  value={item.unit_price}
                  onChange={(e) => update({ unit_price: e.target.value })}
                />
              ),
            },
            {
              key: "tax_rate",
              header: "Tax %",
              width: "14%",
              render: (item, update) => (
                <input
                  type="number"
                  min={0}
                  max={100}
                  step="0.01"
                  className={inlineInputClasses}
                  value={item.tax_rate}
                  onChange={(e) => update({ tax_rate: e.target.value })}
                />
              ),
            },
          ]}
        />
      </form>
    </Modal>
  );
}
