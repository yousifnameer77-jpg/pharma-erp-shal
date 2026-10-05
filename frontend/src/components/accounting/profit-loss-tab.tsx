"use client";

import { useState } from "react";
import { Printer, TrendingUp, TrendingDown, DollarSign } from "lucide-react";
import { useApiResource } from "@/lib/hooks";
import { formatMoney } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Input } from "@/components/ui/field";
import { Button } from "@/components/ui/button";
import { StatCard } from "@/components/ui/stat-card";
import type { ChartOfAccount, ProfitAndLoss } from "@/lib/types";

type Line = { account: ChartOfAccount; amount: number };

function lineColumns(): Column<Line>[] {
  return [
    {
      key: "code",
      header: "Code",
      render: (l) => (
        <span className="font-mono text-xs text-slate-500">
          {l.account?.code ?? "—"}
        </span>
      ),
    },
    { key: "name", header: "Account", render: (l) => l.account?.name ?? "—" },
    {
      key: "amount",
      header: "Amount",
      render: (l) => formatMoney(l.amount),
      className: "text-right",
    },
  ];
}

export function ProfitLossTab({ companyId }: { companyId: string }) {
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  const report = useApiResource<ProfitAndLoss>(
    "/v1/accounting/profit-and-loss",
    {
      company_id: companyId,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
    },
  );

  return (
    <div className="flex flex-col gap-4">
      <div className="no-print flex flex-wrap items-end justify-between gap-3">
        <div className="flex gap-3">
          <Input
            label="From (optional)"
            type="date"
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
            wrapClassName="w-44"
          />
          <Input
            label="To (optional)"
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
            wrapClassName="w-44"
          />
        </div>
        <Button variant="outline" size="sm" onClick={() => window.print()}>
          <Printer className="h-4 w-4" /> Print
        </Button>
      </div>

      {report.data && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <StatCard
            label="Total revenue"
            value={formatMoney(report.data.total_revenue)}
            icon={TrendingUp}
            tone="emerald"
          />
          <StatCard
            label="Total expense"
            value={formatMoney(report.data.total_expense)}
            icon={TrendingDown}
            tone="red"
          />
          <StatCard
            label="Net profit"
            value={formatMoney(report.data.net_profit)}
            icon={DollarSign}
            tone={report.data.net_profit >= 0 ? "brand" : "red"}
          />
        </div>
      )}

      <Card>
        <CardHeader title="Revenue" />
        <DataTable
          columns={lineColumns()}
          rows={report.data?.revenue ?? []}
          rowKey={(l) => l.account?.id ?? String(Math.random())}
          loading={report.loading}
          error={report.error}
          emptyTitle="No revenue in this period"
        />
      </Card>

      <Card>
        <CardHeader title="Expenses" />
        <DataTable
          columns={lineColumns()}
          rows={report.data?.expenses ?? []}
          rowKey={(l) => l.account?.id ?? String(Math.random())}
          loading={report.loading}
          error={report.error}
          emptyTitle="No expenses in this period"
        />
      </Card>
    </div>
  );
}
