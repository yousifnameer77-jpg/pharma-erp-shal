"use client";

import { useState } from "react";
import { useApiResource } from "@/lib/hooks";
import { formatDate, formatMoney, titleCase, todayIso } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Input } from "@/components/ui/field";
import { Drawer, DetailRow, DetailSection } from "@/components/ui/drawer";
import type { AccountLedger, TrialBalanceRow } from "@/lib/types";

export function GeneralLedgerTab({ companyId }: { companyId: string }) {
  const [asOfDate, setAsOfDate] = useState(todayIso());
  const [selectedAccountId, setSelectedAccountId] = useState<string | null>(
    null,
  );
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState(todayIso());

  const trialBalance = useApiResource<TrialBalanceRow[]>(
    "/v1/accounting/general-ledger",
    {
      company_id: companyId,
      as_of_date: asOfDate,
    },
  );
  const ledger = useApiResource<AccountLedger>(
    selectedAccountId
      ? `/v1/accounting/general-ledger/${selectedAccountId}`
      : null,
    { date_from: dateFrom || undefined, date_to: dateTo || undefined },
  );

  const rows = trialBalance.data ?? [];
  const totalDebit = rows.reduce((s, r) => s + r.total_debit, 0);
  const totalCredit = rows.reduce((s, r) => s + r.total_credit, 0);

  const columns: Column<TrialBalanceRow>[] = [
    {
      key: "code",
      header: "Code",
      render: (r) => (
        <span className="font-mono text-xs text-slate-500">
          {r.account.code}
        </span>
      ),
    },
    {
      key: "name",
      header: "Account",
      render: (r) => (
        <span className="font-medium text-slate-900">{r.account.name}</span>
      ),
    },
    { key: "type", header: "Type", render: (r) => titleCase(r.account.type) },
    {
      key: "debit",
      header: "Total debit",
      render: (r) => formatMoney(r.total_debit),
      className: "text-right",
    },
    {
      key: "credit",
      header: "Total credit",
      render: (r) => formatMoney(r.total_credit),
      className: "text-right",
    },
    {
      key: "balance",
      header: "Balance",
      render: (r) => (
        <span className="font-medium text-slate-900">
          {formatMoney(r.balance)}
        </span>
      ),
      className: "text-right",
    },
  ];

  return (
    <Card>
      <CardHeader
        title="Trial balance"
        description="Every account's net balance as of a given date — click an account to see its posting history."
        action={
          <Input
            type="date"
            value={asOfDate}
            onChange={(e) => setAsOfDate(e.target.value)}
            wrapClassName="w-40"
          />
        }
      />
      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(r) => r.account.id}
        loading={trialBalance.loading}
        error={trialBalance.error}
        onRowClick={(r) => setSelectedAccountId(r.account.id)}
        emptyTitle="No postings yet"
      />
      {rows.length > 0 && (
        <div className="flex items-center justify-end gap-6 border-t border-slate-100 px-4 py-3 text-sm">
          <span>
            Total debit{" "}
            <span className="font-semibold text-slate-900">
              {formatMoney(totalDebit)}
            </span>
          </span>
          <span>
            Total credit{" "}
            <span className="font-semibold text-slate-900">
              {formatMoney(totalCredit)}
            </span>
          </span>
        </div>
      )}

      {selectedAccountId && (
        <Drawer
          open
          onClose={() => setSelectedAccountId(null)}
          title={
            ledger.data
              ? `${ledger.data.account.code} — ${ledger.data.account.name}`
              : "Account ledger"
          }
        >
          <div className="mb-4 flex gap-3">
            <Input
              label="From"
              type="date"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              wrapClassName="flex-1"
            />
            <Input
              label="To"
              type="date"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              wrapClassName="flex-1"
            />
          </div>
          {ledger.data && (
            <>
              <DetailSection title="Summary">
                <DetailRow
                  label="Opening balance"
                  value={formatMoney(ledger.data.opening_balance)}
                />
                <DetailRow
                  label="Closing balance"
                  value={formatMoney(ledger.data.closing_balance)}
                />
              </DetailSection>
              <DetailSection title="Transactions">
                <div className="space-y-1">
                  {ledger.data.transactions.length === 0 && (
                    <p className="py-2 text-xs text-slate-400">
                      No postings in this date range.
                    </p>
                  )}
                  {ledger.data.transactions.map((t, i) => (
                    <div
                      key={i}
                      className="flex items-center justify-between gap-3 py-1.5 text-sm"
                    >
                      <div className="min-w-0">
                        <p className="truncate">{t.entry_number}</p>
                        <p className="truncate text-xs text-slate-400">
                          {formatDate(t.date)}{" "}
                          {t.description ? `· ${t.description}` : ""}
                        </p>
                      </div>
                      <div className="shrink-0 text-right">
                        <p className="font-medium">
                          {t.debit > 0
                            ? `Dr ${formatMoney(t.debit)}`
                            : `Cr ${formatMoney(t.credit)}`}
                        </p>
                        <p className="text-xs text-slate-400">
                          Bal {formatMoney(t.running_balance)}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              </DetailSection>
            </>
          )}
        </Drawer>
      )}
    </Card>
  );
}
