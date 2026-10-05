"use client";

import { useMemo, useState } from "react";
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
import type { ChartOfAccount, Expense } from "@/lib/types";

interface DraftItem {
  account_id: string;
  amount: string;
  description: string;
}

function emptyItem(): DraftItem {
  return { account_id: "", amount: "", description: "" };
}

export function ExpensesTab({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.financeManage);
  const toast = useToast();

  const [creating, setCreating] = useState(false);
  const [selected, setSelected] = useState<Expense | null>(null);
  const [busy, setBusy] = useState(false);

  const list = usePaginatedResource<Expense>("/v1/expenses", {
    company_id: companyId,
  });
  const accounts = useSimpleList<ChartOfAccount>("/v1/chart-of-accounts", {
    company_id: companyId,
    is_active: true,
  });
  const accountsById = new Map(accounts.data.map((a) => [a.id, a]));

  async function post(expense: Expense) {
    if (
      !confirm(
        "Post this expense? This debits the expense accounts and credits the paid-from account.",
      )
    )
      return;
    setBusy(true);
    try {
      await api.post(`/v1/expenses/${expense.id}/post`);
      toast.success("Expense posted.");
      list.refetch();
      setSelected(null);
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "Could not post this expense.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function cancel(expense: Expense) {
    if (!confirm("Cancel this expense?")) return;
    setBusy(true);
    try {
      await api.post(`/v1/expenses/${expense.id}/cancel`);
      toast.success("Expense cancelled.");
      list.refetch();
      setSelected(null);
    } catch (err) {
      toast.error(
        err instanceof ApiError
          ? err.summary
          : "Could not cancel this expense.",
      );
    } finally {
      setBusy(false);
    }
  }

  const columns: Column<Expense>[] = [
    {
      key: "number",
      header: "Expense #",
      render: (e) => (
        <span className="font-medium text-slate-900">{e.expense_number}</span>
      ),
    },
    { key: "date", header: "Date", render: (e) => formatDate(e.expense_date) },
    {
      key: "account",
      header: "Paid from",
      render: (e) => accountsById.get(e.paid_from_account_id)?.name ?? "—",
    },
    {
      key: "total",
      header: "Total",
      render: (e) => formatMoney(e.total_amount),
      className: "text-right",
    },
    {
      key: "status",
      header: "Status",
      render: (e) => <StatusBadge status={e.status} />,
    },
  ];

  return (
    <Card>
      <div className="flex items-center justify-end border-b border-slate-100 p-4">
        {canManage && (
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" /> Record expense
          </Button>
        )}
      </div>
      <DataTable
        columns={columns}
        rows={list.data}
        rowKey={(e) => e.id}
        loading={list.loading}
        error={list.error}
        onRowClick={(e) => setSelected(e)}
        emptyTitle="No expenses yet"
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />

      {creating && (
        <ExpenseFormModal
          companyId={companyId}
          accounts={accounts.data}
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
          title={selected.expense_number}
          subtitle={<StatusBadge status={selected.status} />}
        >
          <DetailSection title="Details">
            <DetailRow label="Date" value={formatDate(selected.expense_date)} />
            <DetailRow
              label="Paid from"
              value={
                accountsById.get(selected.paid_from_account_id)?.name ?? "—"
              }
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
                  <span>
                    {item.account
                      ? `${item.account.code} — ${item.account.name}`
                      : item.account_id}
                    {item.description && (
                      <span className="text-slate-400">
                        {" "}
                        · {item.description}
                      </span>
                    )}
                  </span>
                  <span className="font-medium">
                    {formatMoney(item.amount)}
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
                onClick={() => cancel(selected)}
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

function ExpenseFormModal({
  companyId,
  accounts,
  onClose,
  onDone,
}: {
  companyId: string;
  accounts: ChartOfAccount[];
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const { user } = useAuth();
  const [expenseDate, setExpenseDate] = useState(todayIso());
  const [paidFromAccountId, setPaidFromAccountId] = useState("");
  const [notes, setNotes] = useState("");
  const [items, setItems] = useState<DraftItem[]>([emptyItem()]);
  const [busy, setBusy] = useState(false);

  const payFromOptions = useMemo(
    () =>
      accounts.filter(
        (a) =>
          a.type === "asset" &&
          (a.category === "cash" || a.category === "bank"),
      ),
    [accounts],
  );
  const expenseAccountOptions = useMemo(
    () => accounts.filter((a) => a.type === "expense"),
    [accounts],
  );

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await api.post("/v1/expenses", {
        company_id: companyId,
        branch_id: user?.branch?.id,
        expense_date: expenseDate,
        paid_from_account_id: paidFromAccountId,
        notes: notes || undefined,
        items: items
          .filter((i) => i.account_id && i.amount)
          .map((i) => ({
            account_id: i.account_id,
            amount: Number(i.amount),
            description: i.description || undefined,
          })),
      });
      toast.success("Expense recorded as a draft.");
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError
          ? err.summary
          : "Could not record this expense.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title="Record expense"
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button onClick={submit} loading={busy}>
            Record expense
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Input
          label="Expense date"
          type="date"
          required
          value={expenseDate}
          onChange={(e) => setExpenseDate(e.target.value)}
        />
        <Select
          label="Paid from account"
          required
          value={paidFromAccountId}
          onChange={(e) => setPaidFromAccountId(e.target.value)}
          placeholder="Select account"
        >
          {payFromOptions.map((a) => (
            <option key={a.id} value={a.id}>
              {a.code} — {a.name}
            </option>
          ))}
        </Select>
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
              key: "account",
              header: "Expense account",
              width: "45%",
              render: (item, update) => (
                <select
                  className={inlineInputClasses}
                  value={item.account_id}
                  onChange={(e) => update({ account_id: e.target.value })}
                >
                  <option value="">Select account…</option>
                  {expenseAccountOptions.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.code} — {a.name}
                    </option>
                  ))}
                </select>
              ),
            },
            {
              key: "description",
              header: "Description",
              width: "30%",
              render: (item, update) => (
                <input
                  className={inlineInputClasses}
                  value={item.description}
                  onChange={(e) => update({ description: e.target.value })}
                />
              ),
            },
            {
              key: "amount",
              header: "Amount",
              width: "25%",
              render: (item, update) => (
                <input
                  type="number"
                  min={0.001}
                  step="0.001"
                  className={inlineInputClasses}
                  value={item.amount}
                  onChange={(e) => update({ amount: e.target.value })}
                />
              ),
            },
          ]}
        />
      </form>
    </Modal>
  );
}
