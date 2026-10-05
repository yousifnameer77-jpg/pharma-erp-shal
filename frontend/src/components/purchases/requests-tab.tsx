"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { usePaginatedResource, useSimpleList } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { formatDate, todayIso } from "@/lib/format";
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
  PurchaseRequest,
  PurchaseRequestStatus,
  Warehouse,
} from "@/lib/types";

interface DraftItem {
  product_id: string;
  quantity: string;
  notes: string;
}

const STATUS_OPTIONS: PurchaseRequestStatus[] = [
  "draft",
  "submitted",
  "approved",
  "rejected",
  "converted",
  "cancelled",
];

export function PurchaseRequestsTab({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.purchasingManage);
  const canApprove =
    isSuperAdmin || hasPermission(PERMISSIONS.purchasingApprove);
  const toast = useToast();

  const [status, setStatus] = useState("");
  const [creating, setCreating] = useState(false);
  const [selected, setSelected] = useState<PurchaseRequest | null>(null);
  const [converting, setConverting] = useState<PurchaseRequest | null>(null);
  const [busy, setBusy] = useState(false);

  const list = usePaginatedResource<PurchaseRequest>("/v1/purchase-requests", {
    company_id: companyId,
    status: status || undefined,
  });
  const products = useSimpleList<Product>("/v1/products", { per_page: 500 });
  const warehouses = useSimpleList<Warehouse>("/v1/warehouses", {
    branch_id: user?.branch?.id,
  });

  const [newItems, setNewItems] = useState<DraftItem[]>([
    { product_id: "", quantity: "1", notes: "" },
  ]);
  const [warehouseId, setWarehouseId] = useState("");
  const [notes, setNotes] = useState("");

  function resetCreateForm() {
    setNewItems([{ product_id: "", quantity: "1", notes: "" }]);
    setWarehouseId("");
    setNotes("");
  }

  async function submitCreate(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await api.post("/v1/purchase-requests", {
        company_id: companyId,
        branch_id: user?.branch?.id,
        warehouse_id: warehouseId,
        notes: notes || undefined,
        items: newItems
          .filter((i) => i.product_id)
          .map((i) => ({
            product_id: i.product_id,
            quantity: Number(i.quantity),
            notes: i.notes || undefined,
          })),
      });
      toast.success("Purchase request created.");
      setCreating(false);
      resetCreateForm();
      list.refetch();
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "Could not create the request.",
      );
    } finally {
      setBusy(false);
    }
  }

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

  const columns: Column<PurchaseRequest>[] = [
    {
      key: "number",
      header: "Request #",
      render: (r) => (
        <span className="font-medium text-slate-900">{r.request_number}</span>
      ),
    },
    { key: "items", header: "Lines", render: (r) => r.items?.length ?? "—" },
    { key: "date", header: "Created", render: (r) => formatDate(r.created_at) },
    {
      key: "status",
      header: "Status",
      render: (r) => <StatusBadge status={r.status} />,
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
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" /> New request
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
        emptyTitle="No purchase requests yet"
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />

      {creating && (
        <Modal
          open
          onClose={() => setCreating(false)}
          title="New purchase request"
          size="lg"
          footer={
            <>
              <Button
                variant="outline"
                onClick={() => setCreating(false)}
                type="button"
              >
                Cancel
              </Button>
              <Button onClick={submitCreate} loading={busy}>
                Create
              </Button>
            </>
          }
        >
          <form onSubmit={submitCreate} className="grid grid-cols-2 gap-4">
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
            <div />
            <Textarea
              label="Notes"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              wrapClassName="col-span-2"
            />
            <ItemsEditor<DraftItem>
              items={newItems}
              onChange={setNewItems}
              newItem={() => ({ product_id: "", quantity: "1", notes: "" })}
              columns={[
                {
                  key: "product",
                  header: "Product",
                  width: "45%",
                  render: (item, update) => (
                    <select
                      className={inlineInputClasses}
                      value={item.product_id}
                      onChange={(e) => update({ product_id: e.target.value })}
                    >
                      <option value="">Select product…</option>
                      {products.data.map((p) => (
                        <option key={p.id} value={p.id}>
                          {p.name}
                        </option>
                      ))}
                    </select>
                  ),
                },
                {
                  key: "quantity",
                  header: "Quantity",
                  width: "20%",
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
                  key: "notes",
                  header: "Notes",
                  render: (item, update) => (
                    <input
                      className={inlineInputClasses}
                      value={item.notes}
                      onChange={(e) => update({ notes: e.target.value })}
                    />
                  ),
                },
              ]}
            />
          </form>
        </Modal>
      )}

      {selected && (
        <Drawer
          open
          onClose={() => setSelected(null)}
          title={selected.request_number}
          subtitle={<StatusBadge status={selected.status} />}
        >
          <DetailSection title="Details">
            <DetailRow
              label="Created"
              value={formatDate(selected.created_at)}
            />
            <DetailRow label="Notes" value={selected.notes ?? "—"} />
          </DetailSection>
          <DetailSection title="Items">
            <div className="space-y-1">
              {selected.items?.map((item) => (
                <div
                  key={item.id}
                  className="flex items-center justify-between py-1 text-sm"
                >
                  <span>{item.product?.name ?? item.product_id}</span>
                  <span className="font-medium">{item.quantity}</span>
                </div>
              ))}
            </div>
          </DetailSection>

          <div className="flex flex-wrap gap-2 pt-2">
            {canManage && selected.status === "draft" && (
              <Button
                size="sm"
                loading={busy}
                onClick={() =>
                  runAction(
                    () =>
                      api.post(`/v1/purchase-requests/${selected.id}/submit`),
                    "Request submitted.",
                  )
                }
              >
                Submit
              </Button>
            )}
            {canApprove && selected.status === "submitted" && (
              <>
                <Button
                  size="sm"
                  loading={busy}
                  onClick={() =>
                    runAction(
                      () =>
                        api.post(
                          `/v1/purchase-requests/${selected.id}/approve`,
                        ),
                      "Request approved.",
                    )
                  }
                >
                  Approve
                </Button>
                <Button
                  size="sm"
                  variant="danger"
                  loading={busy}
                  onClick={() =>
                    runAction(
                      () =>
                        api.post(`/v1/purchase-requests/${selected.id}/reject`),
                      "Request rejected.",
                    )
                  }
                >
                  Reject
                </Button>
              </>
            )}
            {canManage &&
              ["draft", "submitted", "approved"].includes(selected.status) && (
                <Button
                  size="sm"
                  variant="outline"
                  loading={busy}
                  onClick={() => {
                    if (confirm("Cancel this purchase request?")) {
                      runAction(
                        () =>
                          api.post(
                            `/v1/purchase-requests/${selected.id}/cancel`,
                          ),
                        "Request cancelled.",
                      );
                    }
                  }}
                >
                  Cancel
                </Button>
              )}
            {canManage && selected.status === "approved" && (
              <Button
                size="sm"
                variant="secondary"
                onClick={() => setConverting(selected)}
              >
                Convert to order
              </Button>
            )}
          </div>
        </Drawer>
      )}

      {converting && (
        <ConvertToOrderModal
          request={converting}
          companyId={companyId}
          onClose={() => setConverting(null)}
          onDone={() => {
            setConverting(null);
            setSelected(null);
            list.refetch();
          }}
        />
      )}
    </Card>
  );
}

function ConvertToOrderModal({
  request,
  companyId,
  onClose,
  onDone,
}: {
  request: PurchaseRequest;
  companyId: string;
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const [supplierId, setSupplierId] = useState("");
  const [expectedDate, setExpectedDate] = useState("");
  const [notes, setNotes] = useState("");
  const [prices, setPrices] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const suppliers = useSimpleList<{ id: string; name: string }>(
    "/v1/suppliers",
    { company_id: companyId, per_page: 500 },
  );

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      const unitPrices: Record<string, number> = {};
      for (const item of request.items ?? []) {
        unitPrices[item.product_id] = Number(prices[item.product_id] ?? 0);
      }
      await api.post(`/v1/purchase-requests/${request.id}/convert-to-order`, {
        supplier_id: supplierId,
        expected_date: expectedDate || undefined,
        notes: notes || undefined,
        unit_prices: unitPrices,
      });
      toast.success("Converted to a purchase order.");
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError
          ? err.summary
          : "Could not convert this request.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={`Convert ${request.request_number} to a purchase order`}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button onClick={submit} loading={busy}>
            Create order
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Select
          label="Supplier"
          required
          value={supplierId}
          onChange={(e) => setSupplierId(e.target.value)}
          placeholder="Select supplier"
        >
          {suppliers.data.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </Select>
        <Input
          label="Expected date"
          type="date"
          min={todayIso()}
          value={expectedDate}
          onChange={(e) => setExpectedDate(e.target.value)}
        />
        <Textarea
          label="Notes"
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          wrapClassName="col-span-2"
        />

        <div className="col-span-2">
          <p className="mb-2 text-xs font-medium text-slate-700">Unit prices</p>
          <div className="space-y-2 rounded-lg border border-slate-200 p-3">
            {request.items?.map((item) => (
              <div
                key={item.id}
                className="flex items-center justify-between gap-3"
              >
                <span className="text-sm text-slate-600">
                  {item.product?.name ?? item.product_id}{" "}
                  <span className="text-slate-400">× {item.quantity}</span>
                </span>
                <input
                  type="number"
                  min={0}
                  step="0.001"
                  required
                  className={`${inlineInputClasses} w-32`}
                  value={prices[item.product_id] ?? ""}
                  onChange={(e) =>
                    setPrices((prev) => ({
                      ...prev,
                      [item.product_id]: e.target.value,
                    }))
                  }
                />
              </div>
            ))}
          </div>
        </div>
      </form>
    </Modal>
  );
}
