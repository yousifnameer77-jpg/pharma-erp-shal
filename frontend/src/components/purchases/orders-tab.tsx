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
  Product,
  PurchaseOrder,
  PurchaseOrderStatus,
  Supplier,
  Warehouse,
} from "@/lib/types";

interface DraftItem {
  product_id: string;
  quantity: string;
  unit_price: string;
  tax_rate: string;
}

const STATUS_OPTIONS: PurchaseOrderStatus[] = [
  "draft",
  "submitted",
  "approved",
  "partially_received",
  "received",
  "cancelled",
  "closed",
];

function emptyItem(): DraftItem {
  return { product_id: "", quantity: "1", unit_price: "0", tax_rate: "0" };
}

export function PurchaseOrdersTab({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.purchasingManage);
  const canApprove =
    isSuperAdmin || hasPermission(PERMISSIONS.purchasingApprove);
  const toast = useToast();

  const [status, setStatus] = useState("");
  const [supplierId, setSupplierId] = useState("");
  const [formOpen, setFormOpen] = useState<"new" | PurchaseOrder | null>(null);
  const [selected, setSelected] = useState<PurchaseOrder | null>(null);
  const [busy, setBusy] = useState(false);

  const list = usePaginatedResource<PurchaseOrder>("/v1/purchase-orders", {
    company_id: companyId,
    status: status || undefined,
    supplier_id: supplierId || undefined,
  });
  const suppliers = useSimpleList<Supplier>("/v1/suppliers", {
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

  const columns: Column<PurchaseOrder>[] = [
    {
      key: "number",
      header: "Order #",
      render: (o) => (
        <span className="font-medium text-slate-900">{o.order_number}</span>
      ),
    },
    {
      key: "supplier",
      header: "Supplier",
      render: (o) => o.supplier?.name ?? "—",
    },
    {
      key: "date",
      header: "Order date",
      render: (o) => formatDate(o.order_date),
    },
    {
      key: "expected",
      header: "Expected",
      render: (o) => formatDate(o.expected_date),
    },
    {
      key: "status",
      header: "Status",
      render: (o) => <StatusBadge status={o.status} />,
    },
  ];

  return (
    <Card>
      <div className="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-wrap gap-2">
          <Select
            value={status}
            onChange={(e) => setStatus(e.target.value)}
            placeholder="All statuses"
            className="w-full sm:w-44"
          >
            {STATUS_OPTIONS.map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </Select>
          <Select
            value={supplierId}
            onChange={(e) => setSupplierId(e.target.value)}
            placeholder="All suppliers"
            className="w-full sm:w-44"
          >
            {suppliers.data.map((s) => (
              <option key={s.id} value={s.id}>
                {s.name}
              </option>
            ))}
          </Select>
        </div>
        {canManage && (
          <Button size="sm" onClick={() => setFormOpen("new")}>
            <Plus className="h-4 w-4" /> New order
          </Button>
        )}
      </div>

      <DataTable
        columns={columns}
        rows={list.data}
        rowKey={(o) => o.id}
        loading={list.loading}
        error={list.error}
        onRowClick={(o) => setSelected(o)}
        emptyTitle="No purchase orders yet"
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />

      {formOpen && (
        <OrderFormModal
          companyId={companyId}
          order={formOpen === "new" ? null : formOpen}
          suppliers={suppliers.data}
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
          title={selected.order_number}
          subtitle={<StatusBadge status={selected.status} />}
        >
          <DetailSection title="Details">
            <DetailRow
              label="Supplier"
              value={selected.supplier?.name ?? "—"}
            />
            <DetailRow
              label="Order date"
              value={formatDate(selected.order_date)}
            />
            <DetailRow
              label="Expected date"
              value={formatDate(selected.expected_date)}
            />
            <DetailRow label="Notes" value={selected.notes ?? "—"} />
          </DetailSection>
          <DetailSection title="Items">
            <div className="space-y-2">
              {selected.items?.map((item) => (
                <div
                  key={item.id}
                  className="flex items-center justify-between py-1 text-sm"
                >
                  <div>
                    <p>{item.product?.name ?? item.product_id}</p>
                    <p className="text-xs text-slate-400">
                      {item.quantity} × {formatMoney(item.unit_price)} ·
                      received {item.received_quantity}
                    </p>
                  </div>
                  <span className="font-medium">
                    {formatMoney(item.line_total)}
                  </span>
                </div>
              ))}
            </div>
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
                      () =>
                        api.post(`/v1/purchase-orders/${selected.id}/submit`),
                      "Order submitted.",
                    )
                  }
                >
                  Submit
                </Button>
              </>
            )}
            {canApprove && selected.status === "submitted" && (
              <Button
                size="sm"
                loading={busy}
                onClick={() =>
                  runAction(
                    () =>
                      api.post(`/v1/purchase-orders/${selected.id}/approve`),
                    "Order approved.",
                  )
                }
              >
                Approve
              </Button>
            )}
            {canManage &&
              ["draft", "submitted", "approved"].includes(selected.status) && (
                <Button
                  size="sm"
                  variant="outline"
                  loading={busy}
                  onClick={() => {
                    if (confirm("Cancel this purchase order?")) {
                      runAction(
                        () =>
                          api.post(`/v1/purchase-orders/${selected.id}/cancel`),
                        "Order cancelled.",
                      );
                    }
                  }}
                >
                  Cancel
                </Button>
              )}
          </div>
        </Drawer>
      )}
    </Card>
  );
}

function OrderFormModal({
  companyId,
  order,
  suppliers,
  products,
  warehouses,
  branchId,
  onClose,
  onDone,
}: {
  companyId: string;
  order: PurchaseOrder | null;
  suppliers: Supplier[];
  products: Product[];
  warehouses: Warehouse[];
  branchId: string;
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const isEdit = !!order;
  const [supplierId, setSupplierId] = useState(order?.supplier_id ?? "");
  const [warehouseId, setWarehouseId] = useState(order?.warehouse_id ?? "");
  const [orderDate, setOrderDate] = useState(order?.order_date ?? todayIso());
  const [expectedDate, setExpectedDate] = useState(order?.expected_date ?? "");
  const [notes, setNotes] = useState(order?.notes ?? "");
  const [items, setItems] = useState<DraftItem[]>(
    order?.items?.map((i) => ({
      product_id: i.product_id,
      quantity: i.quantity,
      unit_price: i.unit_price,
      tax_rate: i.tax_rate ?? "0",
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
        }));

      if (isEdit && order) {
        await api.put(`/v1/purchase-orders/${order.id}`, {
          expected_date: expectedDate || undefined,
          notes: notes || undefined,
          items: itemsPayload,
        });
        toast.success("Order updated.");
      } else {
        await api.post("/v1/purchase-orders", {
          company_id: companyId,
          branch_id: branchId,
          warehouse_id: warehouseId,
          supplier_id: supplierId,
          order_date: orderDate,
          expected_date: expectedDate || undefined,
          notes: notes || undefined,
          items: itemsPayload,
        });
        toast.success("Purchase order created.");
      }
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "Could not save this order.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={isEdit ? `Edit ${order?.order_number}` : "New purchase order"}
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button onClick={submit} loading={busy}>
            {isEdit ? "Save changes" : "Create order"}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Select
          label="Supplier"
          required
          disabled={isEdit}
          value={supplierId}
          onChange={(e) => setSupplierId(e.target.value)}
          placeholder="Select supplier"
        >
          {suppliers.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
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
          label="Order date"
          type="date"
          required
          disabled={isEdit}
          value={orderDate}
          onChange={(e) => setOrderDate(e.target.value)}
        />
        <Input
          label="Expected date"
          type="date"
          min={orderDate}
          value={expectedDate}
          onChange={(e) => setExpectedDate(e.target.value)}
        />
        <Textarea
          label="Notes"
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          wrapClassName="col-span-2"
        />

        <ItemsEditor<DraftItem>
          items={items}
          onChange={setItems}
          newItem={emptyItem}
          columns={[
            {
              key: "product",
              header: "Product",
              width: "35%",
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
              width: "15%",
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
              width: "20%",
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
              width: "15%",
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
