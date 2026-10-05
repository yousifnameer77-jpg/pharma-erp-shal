"use client";

import { useMemo, useState } from "react";
import { Plus } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { usePaginatedResource } from "@/lib/hooks";
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
import type { GoodsReceipt, PurchaseOrder } from "@/lib/types";

interface DraftItem {
  purchase_order_item_id: string;
  product_name: string;
  batch_number: string;
  manufacture_date: string;
  expiry_date: string;
  quantity: string;
  unit_cost: string;
}

export function GoodsReceiptsTab({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.purchasingManage);
  const toast = useToast();

  const [creating, setCreating] = useState(false);
  const [selected, setSelected] = useState<GoodsReceipt | null>(null);
  const [busy, setBusy] = useState(false);

  const list = usePaginatedResource<GoodsReceipt>("/v1/goods-receipts", {
    company_id: companyId,
  });
  const orders = usePaginatedResource<PurchaseOrder>("/v1/purchase-orders", {
    company_id: companyId,
    per_page: 200,
  });
  const receivableOrders = useMemo(
    () =>
      orders.data.filter(
        (o) => o.status === "approved" || o.status === "partially_received",
      ),
    [orders.data],
  );

  async function post(receipt: GoodsReceipt) {
    if (
      !confirm(
        "Post this goods receipt? This creates stock batches and a journal entry, and cannot be undone.",
      )
    )
      return;
    setBusy(true);
    try {
      await api.post(`/v1/goods-receipts/${receipt.id}/post`);
      toast.success("Goods receipt posted — stock and accounting updated.");
      list.refetch();
      setSelected(null);
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "Could not post this receipt.",
      );
    } finally {
      setBusy(false);
    }
  }

  const columns: Column<GoodsReceipt>[] = [
    {
      key: "number",
      header: "Receipt #",
      render: (r) => (
        <span className="font-medium text-slate-900">{r.receipt_number}</span>
      ),
    },
    {
      key: "order",
      header: "Purchase order",
      render: (r) => r.purchase_order?.order_number ?? "—",
    },
    { key: "date", header: "Date", render: (r) => formatDate(r.receipt_date) },
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
            <Plus className="h-4 w-4" /> New receipt
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
        emptyTitle="No goods receipts yet"
        emptyDescription="Receipts can only be created against an approved purchase order."
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />

      {creating && (
        <ReceiptFormModal
          companyId={companyId}
          orders={receivableOrders}
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
          title={selected.receipt_number}
          subtitle={<StatusBadge status={selected.status} />}
        >
          <DetailSection title="Details">
            <DetailRow
              label="Purchase order"
              value={selected.purchase_order?.order_number ?? "—"}
            />
            <DetailRow
              label="Receipt date"
              value={formatDate(selected.receipt_date)}
            />
            <DetailRow label="Notes" value={selected.notes ?? "—"} />
          </DetailSection>
          <DetailSection title="Items received">
            <div className="space-y-2">
              {selected.items?.map((item) => (
                <div key={item.id} className="py-1 text-sm">
                  <p>{item.product?.name ?? item.product_id}</p>
                  <p className="text-xs text-slate-400">
                    Batch {item.batch_number} · qty {item.quantity} · cost{" "}
                    {formatMoney(item.unit_cost)} · exp{" "}
                    {formatDate(item.expiry_date)}
                  </p>
                </div>
              ))}
            </div>
          </DetailSection>
          {canManage && selected.status === "draft" && (
            <Button size="sm" loading={busy} onClick={() => post(selected)}>
              Post receipt
            </Button>
          )}
        </Drawer>
      )}
    </Card>
  );
}

function ReceiptFormModal({
  companyId,
  orders,
  onClose,
  onDone,
}: {
  companyId: string;
  orders: PurchaseOrder[];
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const [orderId, setOrderId] = useState("");
  const [receiptDate, setReceiptDate] = useState(todayIso());
  const [items, setItems] = useState<DraftItem[]>([]);
  const [busy, setBusy] = useState(false);

  const order = orders.find((o) => o.id === orderId) ?? null;

  function selectOrder(id: string) {
    setOrderId(id);
    const o = orders.find((x) => x.id === id);
    const pending = (o?.items ?? []).filter((i) => i.remaining_quantity > 0);
    setItems(
      pending.map((i) => ({
        purchase_order_item_id: i.id,
        product_name: i.product?.name ?? i.product_id,
        batch_number: "",
        manufacture_date: "",
        expiry_date: "",
        quantity: String(i.remaining_quantity),
        unit_cost: i.unit_price,
      })),
    );
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!order) return;
    setBusy(true);
    try {
      await api.post("/v1/goods-receipts", {
        company_id: companyId,
        warehouse_id: order.warehouse_id,
        purchase_order_id: order.id,
        receipt_date: receiptDate,
        items: items
          .filter((i) => i.batch_number && i.expiry_date)
          .map((i) => ({
            purchase_order_item_id: i.purchase_order_item_id,
            batch_number: i.batch_number,
            manufacture_date: i.manufacture_date || undefined,
            expiry_date: i.expiry_date,
            quantity: Number(i.quantity),
            unit_cost: Number(i.unit_cost),
          })),
      });
      toast.success(
        "Goods receipt recorded as a draft — post it to update stock.",
      );
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError
          ? err.summary
          : "Could not record this receipt.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title="New goods receipt"
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button onClick={submit} loading={busy} disabled={!order}>
            Record receipt
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Select
          label="Purchase order"
          required
          value={orderId}
          onChange={(e) => selectOrder(e.target.value)}
          placeholder="Select order"
        >
          {orders.map((o) => (
            <option key={o.id} value={o.id}>
              {o.order_number} — {o.supplier?.name}
            </option>
          ))}
        </Select>
        <Input
          label="Receipt date"
          type="date"
          required
          value={receiptDate}
          onChange={(e) => setReceiptDate(e.target.value)}
        />

        {order && items.length > 0 && (
          <ItemsEditor<DraftItem>
            items={items}
            onChange={setItems}
            newItem={() => ({
              purchase_order_item_id: "",
              product_name: "",
              batch_number: "",
              manufacture_date: "",
              expiry_date: "",
              quantity: "0",
              unit_cost: "0",
            })}
            minItems={0}
            columns={[
              {
                key: "product",
                header: "Product",
                width: "22%",
                render: (item) => (
                  <span className="text-sm">{item.product_name}</span>
                ),
              },
              {
                key: "batch",
                header: "Batch #",
                width: "16%",
                render: (item, update) => (
                  <input
                    required
                    className={inlineInputClasses}
                    value={item.batch_number}
                    onChange={(e) => update({ batch_number: e.target.value })}
                  />
                ),
              },
              {
                key: "expiry",
                header: "Expiry date",
                width: "16%",
                render: (item, update) => (
                  <input
                    type="date"
                    required
                    className={inlineInputClasses}
                    value={item.expiry_date}
                    onChange={(e) => update({ expiry_date: e.target.value })}
                  />
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
                key: "cost",
                header: "Unit cost",
                width: "16%",
                render: (item, update) => (
                  <input
                    type="number"
                    min={0}
                    step="0.001"
                    className={inlineInputClasses}
                    value={item.unit_cost}
                    onChange={(e) => update({ unit_cost: e.target.value })}
                  />
                ),
              },
            ]}
          />
        )}
        {order && items.length === 0 && (
          <p className="col-span-2 text-sm text-slate-400">
            Every line on this order has already been fully received.
          </p>
        )}
      </form>
    </Modal>
  );
}
