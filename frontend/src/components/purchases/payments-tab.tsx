"use client";

import { useMemo, useState } from "react";
import { Plus } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { usePaginatedResource, useSimpleList } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { formatDate, formatMoney, todayIso, titleCase } from "@/lib/format";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Pagination } from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";
import { Select, Input, Textarea, Checkbox } from "@/components/ui/field";
import { Drawer, DetailRow, DetailSection } from "@/components/ui/drawer";
import { Modal } from "@/components/ui/modal";
import { inlineInputClasses } from "@/components/forms/items-editor";
import { useToast } from "@/components/ui/toast";
import type {
  ChartOfAccount,
  PaymentMethod,
  PurchaseInvoice,
  Supplier,
  SupplierPayment,
} from "@/lib/types";

const METHODS: PaymentMethod[] = [
  "cash",
  "bank_transfer",
  "cheque",
  "card",
  "other",
];

export function SupplierPaymentsTab({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.financeManage);
  const toast = useToast();

  const [creating, setCreating] = useState(false);
  const [selected, setSelected] = useState<SupplierPayment | null>(null);

  const list = usePaginatedResource<SupplierPayment>("/v1/supplier-payments", {
    company_id: companyId,
  });
  const suppliers = useSimpleList<Supplier>("/v1/suppliers", {
    company_id: companyId,
    per_page: 500,
  });

  const columns: Column<SupplierPayment>[] = [
    {
      key: "number",
      header: "Payment #",
      render: (p) => (
        <span className="font-medium text-slate-900">{p.payment_number}</span>
      ),
    },
    {
      key: "supplier",
      header: "Supplier",
      render: (p) =>
        suppliers.data.find((s) => s.id === p.supplier_id)?.name ??
        p.supplier_id,
    },
    { key: "date", header: "Date", render: (p) => formatDate(p.payment_date) },
    {
      key: "amount",
      header: "Amount",
      render: (p) => formatMoney(p.amount),
      className: "text-right",
    },
    { key: "method", header: "Method", render: (p) => titleCase(p.method) },
  ];

  return (
    <Card>
      <div className="flex items-center justify-end border-b border-slate-100 p-4">
        {canManage && (
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" /> Record payment
          </Button>
        )}
      </div>
      <DataTable
        columns={columns}
        rows={list.data}
        rowKey={(p) => p.id}
        loading={list.loading}
        error={list.error}
        onRowClick={(p) => setSelected(p)}
        emptyTitle="No supplier payments yet"
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />

      {creating && (
        <PaymentFormModal
          companyId={companyId}
          suppliers={suppliers.data}
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
          title={selected.payment_number}
        >
          <DetailSection title="Details">
            <DetailRow
              label="Supplier"
              value={
                suppliers.data.find((s) => s.id === selected.supplier_id)
                  ?.name ?? selected.supplier_id
              }
            />
            <DetailRow label="Date" value={formatDate(selected.payment_date)} />
            <DetailRow label="Amount" value={formatMoney(selected.amount)} />
            <DetailRow label="Method" value={titleCase(selected.method)} />
            <DetailRow label="Reference" value={selected.reference ?? "—"} />
          </DetailSection>
          {selected.allocations && selected.allocations.length > 0 && (
            <DetailSection title="Applied to invoices">
              {selected.allocations.map((a, i) => (
                <DetailRow
                  key={i}
                  label={a.purchase_invoice_id}
                  value={formatMoney(a.amount)}
                />
              ))}
            </DetailSection>
          )}
          <p className="text-xs text-slate-400">
            Payments are final once recorded — there is no edit or cancel
            action.
          </p>
        </Drawer>
      )}
    </Card>
  );
}

function PaymentFormModal({
  companyId,
  suppliers,
  onClose,
  onDone,
}: {
  companyId: string;
  suppliers: Supplier[];
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const [supplierId, setSupplierId] = useState("");
  const [paymentDate, setPaymentDate] = useState(todayIso());
  const [amount, setAmount] = useState("");
  const [method, setMethod] = useState<PaymentMethod>("bank_transfer");
  const [accountId, setAccountId] = useState("");
  const [reference, setReference] = useState("");
  const [currency, setCurrency] = useState("IQD");
  const [notes, setNotes] = useState("");
  const [manualAllocation, setManualAllocation] = useState(false);
  const [allocations, setAllocations] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  const cashBankAccounts = useSimpleList<ChartOfAccount>(
    "/v1/chart-of-accounts",
    { company_id: companyId },
  );
  const payAccounts = useMemo(
    () =>
      cashBankAccounts.data.filter(
        (a) => a.category === "cash" || a.category === "bank",
      ),
    [cashBankAccounts.data],
  );

  const invoices = usePaginatedResource<PurchaseInvoice>(
    "/v1/purchase-invoices",
    {
      company_id: companyId,
      supplier_id: supplierId || undefined,
      per_page: 200,
    },
  );
  const outstanding = useMemo(
    () =>
      invoices.data.filter(
        (i) =>
          (i.status === "posted" || i.status === "partially_paid") &&
          ((i as any).currency ?? "IQD") === currency,
      ),
    [invoices.data, currency],
  );

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      const payload: Record<string, unknown> = {
        company_id: companyId,
        supplier_id: supplierId,
        payment_date: paymentDate,
        amount: Number(amount),
        method,
        currency,
        paid_from_account_id: accountId,
        reference: reference || undefined,
        notes: notes || undefined,
      };
      if (manualAllocation) {
        payload.allocations = Object.entries(allocations)
          .filter(([, v]) => Number(v) > 0)
          .map(([purchase_invoice_id, v]) => ({
            purchase_invoice_id,
            amount: Number(v),
          }));
      }
      await api.post("/v1/supplier-payments", payload);
      toast.success("Payment recorded.");
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError
          ? err.summary
          : "Could not record this payment.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title="Record supplier payment"
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button onClick={submit} loading={busy}>
            Record payment
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
          {suppliers.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </Select>
        <Input
          label="Payment date"
          type="date"
          required
          value={paymentDate}
          onChange={(e) => setPaymentDate(e.target.value)}
        />
        <Select
          label="العملة / Currency"
          value={currency}
          onChange={(e) => {
            setCurrency(e.target.value);
            setAllocations({});
          }}
        >
          <option value="IQD">دينار عراقي (IQD)</option>
          <option value="USD">دولار (USD) — بسعر يوم الدفعة</option>
        </Select>
        <Input
          label="Amount"
          type="number"
          min={0.001}
          step="0.001"
          required
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
        />
        <Select
          label="Method"
          required
          value={method}
          onChange={(e) => setMethod(e.target.value as PaymentMethod)}
        >
          {METHODS.map((m) => (
            <option key={m} value={m}>
              {titleCase(m)}
            </option>
          ))}
        </Select>
        <Select
          label="Paid from account"
          required
          value={accountId}
          onChange={(e) => setAccountId(e.target.value)}
          placeholder="Select account"
        >
          {payAccounts.map((a) => (
            <option key={a.id} value={a.id}>
              {a.code} — {a.name}
            </option>
          ))}
        </Select>
        <Input
          label="Reference"
          value={reference}
          onChange={(e) => setReference(e.target.value)}
        />
        <Textarea
          label="Notes"
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          wrapClassName="col-span-2"
        />

        <div className="col-span-2">
          <Checkbox
            label="Apply to specific invoices instead of oldest-first auto-allocation"
            checked={manualAllocation}
            onChange={(e) => setManualAllocation(e.target.checked)}
          />
        </div>

        {manualAllocation && supplierId && (
          <div className="col-span-2 space-y-2 rounded-lg border border-slate-200 p-3">
            {outstanding.length === 0 && (
              <p className="text-xs text-slate-400">
                No outstanding invoices for this supplier.
              </p>
            )}
            {outstanding.map((inv) => (
              <div
                key={inv.id}
                className="flex items-center justify-between gap-3"
              >
                <span className="text-sm text-slate-600">
                  {inv.invoice_number}{" "}
                  <span className="text-slate-400">
                    — due {formatMoney(inv.remaining_due)}
                  </span>
                </span>
                <input
                  type="number"
                  min={0}
                  step="0.001"
                  className={`${inlineInputClasses} w-32`}
                  value={allocations[inv.id] ?? ""}
                  onChange={(e) =>
                    setAllocations((prev) => ({
                      ...prev,
                      [inv.id]: e.target.value,
                    }))
                  }
                />
              </div>
            ))}
          </div>
        )}
      </form>
    </Modal>
  );
}
