"use client";

import { useMemo, useState } from "react";
import { Plus } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { usePaginatedResource, useSimpleList } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { formatDate, formatMoney, todayIso, toNumber } from "@/lib/format";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Pagination } from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";
import { Select, Input } from "@/components/ui/field";
import { Modal } from "@/components/ui/modal";
import { Drawer, DetailRow, DetailSection } from "@/components/ui/drawer";
import {
  ItemsEditor,
  inlineInputClasses,
} from "@/components/forms/items-editor";
import { useToast } from "@/components/ui/toast";
import type { ChartOfAccount, JournalEntry } from "@/lib/types";

interface DraftLine {
  account_id: string;
  debit: string;
  credit: string;
  description: string;
}

function emptyLine(): DraftLine {
  return { account_id: "", debit: "", credit: "", description: "" };
}

export function JournalEntriesTab({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.financeManage);

  const [creating, setCreating] = useState(false);
  const [selected, setSelected] = useState<JournalEntry | null>(null);

  const list = usePaginatedResource<JournalEntry>("/v1/journal-entries", {
    company_id: companyId,
  });

  function lineTotals(entry: JournalEntry) {
    const debit = (entry.lines ?? []).reduce(
      (s, l) => s + toNumber(l.debit),
      0,
    );
    const credit = (entry.lines ?? []).reduce(
      (s, l) => s + toNumber(l.credit),
      0,
    );
    return { debit, credit };
  }

  const columns: Column<JournalEntry>[] = [
    {
      key: "number",
      header: "Entry #",
      render: (e) => (
        <span className="font-medium text-slate-900">{e.entry_number}</span>
      ),
    },
    { key: "date", header: "Date", render: (e) => formatDate(e.entry_date) },
    { key: "description", header: "Description", render: (e) => e.description },
    {
      key: "reference",
      header: "Source",
      render: (e) => e.reference_type ?? "Manual",
    },
    {
      key: "total",
      header: "Total",
      render: (e) => formatMoney(lineTotals(e).debit),
      className: "text-right",
    },
  ];

  return (
    <Card>
      <div className="flex items-center justify-between gap-3 border-b border-slate-100 p-4">
        <p className="text-xs text-slate-400">
          Sales, purchases, payments and expenses post their own entries
          automatically — use manual entries only for adjustments and
          corrections.
        </p>
        {canManage && (
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" /> Manual entry
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
        emptyTitle="No journal entries yet"
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />

      {creating && (
        <JournalEntryFormModal
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
          title={selected.entry_number}
          subtitle={formatDate(selected.entry_date)}
        >
          <DetailSection title="Details">
            <DetailRow label="Description" value={selected.description} />
            <DetailRow
              label="Source"
              value={selected.reference_type ?? "Manual"}
            />
          </DetailSection>
          <DetailSection title="Lines">
            <div className="space-y-1">
              {selected.lines?.map((line) => (
                <div
                  key={line.id}
                  className="flex items-center justify-between py-1 text-sm"
                >
                  <span>
                    {line.account
                      ? `${line.account.code} — ${line.account.name}`
                      : line.account_id}
                    {line.description && (
                      <span className="text-slate-400">
                        {" "}
                        · {line.description}
                      </span>
                    )}
                  </span>
                  <span className="font-medium">
                    {toNumber(line.debit) > 0
                      ? `Dr ${formatMoney(line.debit)}`
                      : `Cr ${formatMoney(line.credit)}`}
                  </span>
                </div>
              ))}
            </div>
          </DetailSection>
        </Drawer>
      )}
    </Card>
  );
}

function JournalEntryFormModal({
  companyId,
  onClose,
  onDone,
}: {
  companyId: string;
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const { user } = useAuth();
  const [entryDate, setEntryDate] = useState(todayIso());
  const [description, setDescription] = useState("");
  const [lines, setLines] = useState<DraftLine[]>([emptyLine(), emptyLine()]);
  const [busy, setBusy] = useState(false);

  const accounts = useSimpleList<ChartOfAccount>("/v1/chart-of-accounts", {
    company_id: companyId,
    is_active: true,
  });

  const totals = useMemo(() => {
    const debit = lines.reduce((s, l) => s + toNumber(l.debit), 0);
    const credit = lines.reduce((s, l) => s + toNumber(l.credit), 0);
    return {
      debit,
      credit,
      balanced: Math.abs(debit - credit) < 0.005 && debit > 0,
    };
  }, [lines]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await api.post("/v1/journal-entries", {
        company_id: companyId,
        branch_id: user?.branch?.id,
        entry_date: entryDate,
        description,
        lines: lines
          .filter((l) => l.account_id && (l.debit || l.credit))
          .map((l) => ({
            account_id: l.account_id,
            debit: l.debit ? Number(l.debit) : undefined,
            credit: l.credit ? Number(l.credit) : undefined,
            description: l.description || undefined,
          })),
      });
      toast.success("Journal entry posted.");
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "Could not post this entry.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title="New manual journal entry"
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            Cancel
          </Button>
          <Button onClick={submit} loading={busy}>
            Post entry
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Input
          label="Entry date"
          type="date"
          required
          value={entryDate}
          onChange={(e) => setEntryDate(e.target.value)}
        />
        <Input
          label="Description"
          required
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          wrapClassName="col-span-1"
        />

        <ItemsEditor<DraftLine>
          items={lines}
          onChange={setLines}
          newItem={emptyLine}
          minItems={2}
          columns={[
            {
              key: "account",
              header: "Account",
              width: "40%",
              render: (line, update) => (
                <select
                  className={inlineInputClasses}
                  value={line.account_id}
                  onChange={(e) => update({ account_id: e.target.value })}
                >
                  <option value="">Select account…</option>
                  {accounts.data.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.code} — {a.name}
                    </option>
                  ))}
                </select>
              ),
            },
            {
              key: "description",
              header: "Line note",
              width: "22%",
              render: (line, update) => (
                <input
                  className={inlineInputClasses}
                  value={line.description}
                  onChange={(e) => update({ description: e.target.value })}
                />
              ),
            },
            {
              key: "debit",
              header: "Debit",
              width: "18%",
              render: (line, update) => (
                <input
                  type="number"
                  min={0}
                  step="0.001"
                  className={inlineInputClasses}
                  value={line.debit}
                  onChange={(e) =>
                    update({
                      debit: e.target.value,
                      credit: e.target.value ? "" : line.credit,
                    })
                  }
                />
              ),
            },
            {
              key: "credit",
              header: "Credit",
              width: "18%",
              render: (line, update) => (
                <input
                  type="number"
                  min={0}
                  step="0.001"
                  className={inlineInputClasses}
                  value={line.credit}
                  onChange={(e) =>
                    update({
                      credit: e.target.value,
                      debit: e.target.value ? "" : line.debit,
                    })
                  }
                />
              ),
            },
          ]}
        />

        <div className="col-span-2 flex items-center justify-end gap-6 rounded-lg border border-slate-200 bg-slate-50/60 px-4 py-2 text-sm">
          <span>
            Debit total{" "}
            <span className="font-semibold text-slate-900">
              {formatMoney(totals.debit)}
            </span>
          </span>
          <span>
            Credit total{" "}
            <span className="font-semibold text-slate-900">
              {formatMoney(totals.credit)}
            </span>
          </span>
          <span
            className={
              totals.balanced
                ? "font-medium text-emerald-600"
                : "font-medium text-amber-600"
            }
          >
            {totals.balanced ? "Balanced" : "Not balanced yet"}
          </span>
        </div>
      </form>
    </Modal>
  );
}
