"use client";

import { useState } from "react";
import { useApiResource } from "@/lib/hooks";
import { formatMoney, todayIso } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { StatCard } from "@/components/ui/stat-card";
import { Input } from "@/components/ui/field";
import { Wallet, Landmark } from "lucide-react";
import type { TrialBalanceRow } from "@/lib/types";

function accountColumns(): Column<TrialBalanceRow>[] {
  return [
    {
      key: "code",
      header: "Code",
      render: (r) => (
        <span className="font-mono text-xs text-slate-500">
          {r.account?.code ?? "—"}
        </span>
      ),
    },
    {
      key: "name",
      header: "Account",
      render: (r) => (
        <span className="font-medium text-slate-900">{r.account?.name ?? "—"}</span>
      ),
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
}

export function CashBanksTab({ companyId }: { companyId: string }) {
  const [asOfDate, setAsOfDate] = useState(todayIso());

  const cash = useApiResource<TrialBalanceRow[]>("/v1/accounting/cash", {
    company_id: companyId,
    as_of_date: asOfDate,
  });
  const banks = useApiResource<TrialBalanceRow[]>("/v1/accounting/banks", {
    company_id: companyId,
    as_of_date: asOfDate,
  });

  const cashTotal = (cash.data ?? []).reduce((s, r) => s + r.balance, 0);
  const banksTotal = (banks.data ?? []).reduce((s, r) => s + r.balance, 0);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-end">
        <Input
          label="As of date"
          type="date"
          value={asOfDate}
          onChange={(e) => setAsOfDate(e.target.value)}
          wrapClassName="w-44"
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <StatCard
          label="Total cash on hand"
          value={formatMoney(cashTotal)}
          icon={Wallet}
          tone="emerald"
        />
        <StatCard
          label="Total in banks"
          value={formatMoney(banksTotal)}
          icon={Landmark}
          tone="blue"
        />
      </div>

      <Card>
        <CardHeader title="Cash accounts" />
        <DataTable
          columns={accountColumns()}
          rows={cash.data ?? []}
          rowKey={(r) => r.account?.id ?? String(Math.random())}
          loading={cash.loading}
          error={cash.error}
          emptyTitle="No cash accounts"
        />
      </Card>

      <Card>
        <CardHeader title="Bank accounts" />
        <DataTable
          columns={accountColumns()}
          rows={banks.data ?? []}
          rowKey={(r) => r.account?.id ?? String(Math.random())}
          loading={banks.loading}
          error={banks.error}
          emptyTitle="No bank accounts"
        />
      </Card>
    </div>
  );
}
