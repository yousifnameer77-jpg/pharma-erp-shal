"use client";

import { useState } from "react";
import { Printer } from "lucide-react";
import { useApiResource } from "@/lib/hooks";
import { formatMoney, todayIso } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Input } from "@/components/ui/field";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { DetailRow } from "@/components/ui/drawer";
import type { BalanceSheet, ChartOfAccount } from "@/lib/types";

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

export function BalanceSheetTab({ companyId }: { companyId: string }) {
  const [asOfDate, setAsOfDate] = useState(todayIso());

  const report = useApiResource<BalanceSheet>("/v1/accounting/balance-sheet", {
    company_id: companyId,
    as_of_date: asOfDate,
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="no-print flex flex-wrap items-end justify-between gap-3">
        <Input
          label="As of date"
          type="date"
          value={asOfDate}
          onChange={(e) => setAsOfDate(e.target.value)}
          wrapClassName="w-44"
        />
        <div className="flex items-center gap-3">
          {report.data && (
            <Badge tone={report.data.is_balanced ? "emerald" : "red"}>
              {report.data.is_balanced ? "Balanced" : "Out of balance"}
            </Badge>
          )}
          <Button variant="outline" size="sm" onClick={() => window.print()}>
            <Printer className="h-4 w-4" /> Print
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader
          title="Assets"
          action={
            report.data && (
              <span className="text-sm font-semibold text-slate-900">
                {formatMoney(report.data.total_assets)}
              </span>
            )
          }
        />
        <DataTable
          columns={lineColumns()}
          rows={report.data?.assets ?? []}
          rowKey={(l) => l.account?.id ?? String(Math.random())}
          loading={report.loading}
          error={report.error}
          emptyTitle="No asset balances"
        />
      </Card>

      <Card>
        <CardHeader
          title="Liabilities"
          action={
            report.data && (
              <span className="text-sm font-semibold text-slate-900">
                {formatMoney(report.data.total_liabilities)}
              </span>
            )
          }
        />
        <DataTable
          columns={lineColumns()}
          rows={report.data?.liabilities ?? []}
          rowKey={(l) => l.account?.id ?? String(Math.random())}
          loading={report.loading}
          error={report.error}
          emptyTitle="No liability balances"
        />
      </Card>

      <Card>
        <CardHeader
          title="Equity"
          action={
            report.data && (
              <span className="text-sm font-semibold text-slate-900">
                {formatMoney(report.data.total_equity)}
              </span>
            )
          }
        />
        <DataTable
          columns={lineColumns()}
          rows={report.data?.equity ?? []}
          rowKey={(l) => l.account?.id ?? String(Math.random())}
          loading={report.loading}
          error={report.error}
          emptyTitle="No equity balances"
        />
        {report.data && (
          <div className="border-t border-slate-100 px-5 py-3">
            <DetailRow
              label="Current earnings (revenue − expense, synthetic plug)"
              value={formatMoney(report.data.current_earnings)}
            />
          </div>
        )}
      </Card>
    </div>
  );
}
