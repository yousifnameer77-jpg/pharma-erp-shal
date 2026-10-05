"use client";

import { useMemo, useState } from "react";
import { DollarSign, TrendingDown, TrendingUp } from "lucide-react";
import { useApiResource } from "@/lib/hooks";
import { formatMoney } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Input } from "@/components/ui/field";
import { StatCard } from "@/components/ui/stat-card";
import { Badge } from "@/components/ui/badge";
import {
  ReportExportBar,
  type ExportColumn,
} from "@/components/reports/export-bar";
import type { ChartOfAccount, ProfitAndLoss } from "@/lib/types";

interface Row {
  type: "Revenue" | "Expense";
  account: ChartOfAccount;
  amount: number;
}

export function ProfitLossReport({ companyId }: { companyId: string }) {
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

  const rows: Row[] = useMemo(() => {
    if (!report.data) return [];
    return [
      ...report.data.revenue.map((l) => ({
        type: "Revenue" as const,
        account: l.account,
        amount: l.amount,
      })),
      ...report.data.expenses.map((l) => ({
        type: "Expense" as const,
        account: l.account,
        amount: l.amount,
      })),
    ];
  }, [report.data]);

  const columns: Column<Row>[] = [
    {
      key: "type",
      header: "Type",
      render: (r) => (
        <Badge tone={r.type === "Revenue" ? "emerald" : "red"}>{r.type}</Badge>
      ),
    },
    {
      key: "code",
      header: "Code",
      render: (r) => (
        <span className="font-mono text-xs text-slate-500">
          {r.account.code}
        </span>
      ),
    },
    { key: "name", header: "Account", render: (r) => r.account.name },
    {
      key: "amount",
      header: "Amount",
      render: (r) => formatMoney(r.amount),
      className: "text-right",
    },
  ];

  const exportColumns: ExportColumn<Row>[] = [
    { header: "Type", value: (r) => r.type },
    { header: "Code", value: (r) => r.account.code },
    { header: "Account", value: (r) => r.account.name },
    { header: "Amount", value: (r) => formatMoney(r.amount) },
  ];

  const rangeLabel = `${dateFrom || "all time"}_to_${dateTo || "now"}`;

  return (
    <div className="flex flex-col gap-4">
      <ReportExportBar
        title="Profit & Loss Report"
        filename={`profit-loss-${rangeLabel}`}
        columns={exportColumns}
        rows={rows}
        extra={
          <>
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
          </>
        }
      />

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
        <CardHeader
          title="Revenue & expense breakdown"
          description="Every account with net movement in the selected period."
        />
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(r) => `${r.type}-${r.account.id}`}
          loading={report.loading}
          error={report.error}
          emptyTitle="No activity in this period"
        />
      </Card>
    </div>
  );
}
