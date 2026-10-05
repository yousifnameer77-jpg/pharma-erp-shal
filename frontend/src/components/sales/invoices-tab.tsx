"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { usePaginatedResource, useSimpleList } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { formatDate, formatMoney, todayIso } from "@/lib/format";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Pagination } from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";
import { Select, Input, Textarea } from "@/components/ui/field";
import { StatusBadge } from "@/components/ui/badge";
import { Modal } from "@/components/ui/modal";
import { Drawer, DetailRow, DetailSection } from "@/components/ui/drawer";
import {
  ItemsEditor,
  inlineInputClasses,
} from "@/components/forms/items-editor";
import { useToast } from "@/components/ui/toast";
import type {
  Customer,
  Product,
  SalesInvoice,
  SalesInvoiceStatus,
  Warehouse,
} from "@/lib/types";

interface DraftItem {
  product_id: string;
  quantity: string;
  unit_price: string;
  tax_rate: string;
  discount_rate: string;
}

const STATUS_OPTIONS: SalesInvoiceStatus[] = [
  "draft",
  "posted",
  "partially_paid",
  "paid",
  "cancelled",
];

function emptyItem(): DraftItem {
  return {
    product_id: "",
    quantity: "1",
    unit_price: "0",
    tax_rate: "0",
    discount_rate: "0",
  };
}

export function SalesInvoicesTab({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.salesManage);
  const toast = useToast();

  const [status, setStatus] = useState("");
  const [formOpen, setFormOpen] = useState<"new" | SalesInvoice | null>(null);
  const [selected, setSelected] = useState<SalesInvoice | null>(null);
  const [busy, setBusy] = useState(false);

  const list = usePaginatedResource<SalesInvoice>("/v1/sales-invoices", {
    company_id: companyId,
    status: status || undefined,
  });
  const customers = useSimpleList<Customer>("/v1/customers", {
    company_id: companyId,
    per_page: 500,
  });
  const products = useSimpleList<Product>("/v1/products", { per_page: 500 });
  const warehouses = useSimpleList<Warehouse>("/v1/warehouses", {
    branch_id: user?.branch?.id,
  });

  async function runAction(action: () => Promise<unknown>, successMsg: string) {
    setBusy(true);
    try {
      await action();
      toast.success(successMsg);
      list.refetch();
      setSelected(null);
    } catch (err) {
      toast.error(err instanceof ApiError ? err.summary : "Action failed.");
    } finally {
      setBusy(false);
    }
  }

  const columns: Column<SalesInvoice>[] = [
    {
      key: "number",
      header: "Invoice #",
      render: (i) => (
        <span className="font-medium text-slate-900">{i.invoice_number}</span>
      ),
    },
    {
      key: "customer",
      header: "Customer",
      render: (i) => i.customer?.name ?? "—",
    },
    { key: "date", header: "Date", render: (i) => formatDate(i.invoice_date) },
    {
      key: "total",
      header: "Total",
      render: (i) => formatMoney(i.total_amount),
      className: "text-right",
    },
    {
      key: "due",
      header: "Remaining",
      render: (i) => formatMoney(i.remaining_due),
      className: "text-right",
    },
    {
      key: "status",
      header: "Status",
      render: (i) => <StatusBadge status={i.status} />,
    },
  ];

  return (
    <Card>
      <div className="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <Select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          placeholder="All statuses"
          className="w-full sm:w-48"
        >
          {STATUS_OPTIONS.map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </Select>
        {canManage && (
          <Button size="sm" onClick={() => setFormOpen("new")}>
            <Plus className="h-4 w-4" /> New invoice
          </Button>
        )}
      </div>

      <DataTable
        columns={columns}
        rows={list.data}
        rowKey={(i) => i.id}
        loading={list.loading}
        error={list.error}
        onRowClick={(i) => setSelected(i)}
        emptyTitle="No sales invoices yet"
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />

      {formOpen && (
        <InvoiceFormModal
          companyId={companyId}
          invoice={formOpen === "new" ? null : formOpen}
          customers={customers.data}
          products={products.data}
          warehouses={warehouses.data}
          branchId={user?.branch?.id ?? ""}
          onClose={() => setFormOpen(null)}
          onDone={() => {
            setFormOpen(null);
            setSelected(null);
            list.refetch();
          }}
        />
      )}

      {selected && (
        <Drawer
          open
          onClose={() => setSelected(null)}
          title={selected.invoice_number}
          subtitle={<StatusBadge status={selected.status} />}
        >
          <DetailSection title="Details">
            <DetailRow
              label="Customer"
              value={selected.customer?.name ?? "—"}
            />
            <DetailRow
              label="Invoice date"
              value={formatDate(selected.invoice_date)}
            />
            <DetailRow label="Due date" value={formatDate(selected.due_date)} />
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
          <DetailSection title="Totals">
            <DetailRow
              label="Subtotal"
              value={formatMoney(selected.subtotal)}
            />
            <DetailRow
              label="Discount"
              value={formatMoney(selected.discount_amount)}
            />
            <DetailRow label="Tax" value={formatMoney(selected.tax_amount)} />
            <DetailRow
              label="Total"
              value={formatMoney(selected.total_amount)}
            />
            <DetailRow label="Paid" value={formatMoney(selected.paid_amount)} />
            <DetailRow
              label="Remaining due"
              value={formatMoney(selected.remaining_due)}
            />
          </DetailSection>

          <div className="flex flex-wrap gap-2 pt-2">
            {canManage && selected.status === "draft" && (
              <>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setFormOpen(selected)}
                >
                  Edit
                </Button>
                <Button
                  size="sm"
                  loading={busy}
                  onClick={() =>
                    runAction(
                      () => api.post(`/v1/sales-invoices/${selected.id}/post`),
                      "Invoice posted — stock and accounting updated.",
                    )
                  }
                >
                  Post
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  loading={busy}
                  onClick={() => {
                    if (confirm("Cancel this invoice?")) {
                      runAction(
                        () =>
                          api.post(`/v1/sales-invoices/${selected.id}/cancel`),
                        "Invoice cancelled.",
                      );
                    }
                  }}
                >
                  Cancel
                </Button>
              </>
            )}
          </div>
        </Drawer>
      )}
    </Card>
  );
}

function InvoiceFormModal({
  companyId,
  invoice,
  customers,
  products,
  warehouses,
  branchId,
  onClose,
  onDone,
}: {
  companyId: string;
  invoice: SalesInvoice | null;
  customers: Customer[];
  products: Product[];
  warehouses: Warehouse[];
  branchId: string;
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const isEdit = !!invoice;
  const [customerId, setCustomerId] = useState(invoice?.customer_id ?? "");
  const [warehouseId, setWarehouseId] = useState(invoice?.warehouse_id ?? "");
  const [invoiceDate, setInvoiceDate] = useState(
    invoice?.invoice_date ?? todayIso(),
  );
  const [dueDate, setDueDate] = useState(invoice?.due_date ?? "");
  const [notes, setNotes] = useState(invoice?.notes ?? "");
  const [items, setItems] = useState<DraftItem[]>(
    invoice?.items?.map((i) => ({
      product_id: i.product_id,
      quantity: i.quantity,
      unit_price: i.unit_price,
      tax_rate: i.tax_rate ?? "0",
      discount_rate: i.discount_rate ?? "0",
    })) ?? [emptyItem()],
  );
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      const itemsPayload = items
        .filter((i) => i.product_id)
        .map((i) => ({
          product_id: i.product_id,
          quantity: Number(i.quantity),
          unit_price: Number(i.unit_price),
          tax_rate: i.tax_rate ? Number(i.tax_rate) : undefined,
          discount_rate: i.discount_rate ? Number(i.discount_rate) : undefined,
        }));

      if (isEdit && invoice) {
        await api.put(`/v1/sales-invoices/${invoice.id}`, {
          due_date: dueDate || undefined,
          notes: notes || undefined,
          items: itemsPayload,
        });
        toast.success("Invoice updated.");
      } else {
        await api.post("/v1/sales-invoices", {
          company_id: companyId,
          branch_id: branchId,
          warehouse_id: warehouseId,
          customer_id: customerId,
          invoice_date: invoiceDate,
          due_date: dueDate || undefined,
          notes: notes || undefined,
          items: itemsPayload,
        });
        toast.success("Sales invoice created as a draft.");
      }
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "Could not save this invoice.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={isEdit ? `Edit ${invoice?.invoice_number}` : "New sales invoice"}
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button onClick={submit} loading={busy}>
            {isEdit ? "Save changes" : "Create invoice"}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Select
          label="Customer"
          required
          disabled={isEdit}
          value={customerId}
          onChange={(e) => setCustomerId(e.target.value)}
          placeholder="Select customer"
        >
          {customers.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </Select>
        <Select
          label="Warehouse"
          required
          disabled={isEdit}
          value={warehouseId}
          onChange={(e) => setWarehouseId(e.target.value)}
          placeholder="Select warehouse"
        >
          {warehouses.map((w) => (
            <option key={w.id} value={w.id}>
              {w.name}
            </option>
          ))}
        </Select>
        <Input
          label="Invoice date"
          type="date"
          required
          disabled={isEdit}
          value={invoiceDate}
          onChange={(e) => setInvoiceDate(e.target.value)}
        />
        <Input
          label="Due date"
          type="date"
          min={invoiceDate}
          value={dueDate}
          onChange={(e) => setDueDate(e.target.value)}
        />
        <Textarea
          label="Notes"
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          wrapClassName="col-span-2"
        />

        <div className="col-span-2 -mb-2 text-xs text-slate-400">
          Batches are chosen automatically by FEFO (first-expiry-first-out) when
          the invoice is posted.
        </div>
        <ItemsEditor<DraftItem>
          items={items}
          onChange={setItems}
          newItem={emptyItem}
          columns={[
            {
              key: "product",
              header: "Product",
              width: "30%",
              render: (item, update) => (
                <select
                  className={inlineInputClasses}
                  value={item.product_id}
                  onChange={(e) => update({ product_id: e.target.value })}
                >
                  <option value="">Select…</option>
                  {products.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
                </select>
              ),
            },
            {
              key: "quantity",
              header: "Qty",
              width: "12%",
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
              key: "discount_rate",
              header: "Disc %",
              width: "12%",
              render: (item, update) => (
                <input
                  type="number"
                  min={0}
                  max={100}
                  step="0.01"
                  className={inlineInputClasses}
                  value={item.discount_rate}
                  onChange={(e) => update({ discount_rate: e.target.value })}
                />
              ),
            },
            {
              key: "tax_rate",
              header: "Tax %",
              width: "12%",
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
