"use client";

import { useState } from "react";
import { useApiResource } from "@/lib/hooks";
import { formatMoney, todayIso } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Input } from "@/components/ui/field";
import { StatCard } from "@/components/ui/stat-card";
import { Wallet } from "lucide-react";
import {
  ReportExportBar,
  type ExportColumn,
} from "@/components/reports/export-bar";
import type { ReceivablesAging } from "@/lib/types";

type Row = ReceivablesAging["customers"][number];

export function CustomerDebtReport({ companyId }: { companyId: string }) {
  const [asOfDate, setAsOfDate] = useState(todayIso());

  const report = useApiResource<ReceivablesAging>(
    "/v1/accounting/receivables",
    {
      company_id: companyId,
      as_of_date: asOfDate,
    },
  );
  const rows = report.data?.customers ?? [];

  const columns: Column<Row>[] = [
    {
      key: "customer",
      header: "Customer",
      render: (r) => (
        <span className="font-medium text-slate-900">{r.customer.name}</span>
      ),
    },
    {
      key: "current",
      header: "Current",
      render: (r) => formatMoney(r.buckets.current),
      className: "text-right",
    },
    {
      key: "1_30",
      header: "1–30 days",
      render: (r) => formatMoney(r.buckets["1_30"]),
      className: "text-right",
    },
    {
      key: "31_60",
      header: "31–60 days",
      render: (r) => formatMoney(r.buckets["31_60"]),
      className: "text-right",
    },
    {
      key: "61_90",
      header: "61–90 days",
      render: (r) => formatMoney(r.buckets["61_90"]),
      className: "text-right",
    },
    {
      key: "90_plus",
      header: "90+ days",
      render: (r) => formatMoney(r.buckets["90_plus"]),
      className: "text-right",
    },
    {
      key: "total",
      header: "Total owed",
      render: (r) => (
        <span className="font-semibold text-slate-900">
          {formatMoney(r.total)}
        </span>
      ),
      className: "text-right",
    },
  ];

  const exportColumns: ExportColumn<Row>[] = [
    { header: "Customer", value: (r) => r.customer.name },
    { header: "Current", value: (r) => formatMoney(r.buckets.current) },
    { header: "1-30 days", value: (r) => formatMoney(r.buckets["1_30"]) },
    { header: "31-60 days", value: (r) => formatMoney(r.buckets["31_60"]) },
    { header: "61-90 days", value: (r) => formatMoney(r.buckets["61_90"]) },
    { header: "90+ days", value: (r) => formatMoney(r.buckets["90_plus"]) },
    { header: "Total owed", value: (r) => formatMoney(r.total) },
  ];

  return (
    <div className="flex flex-col gap-4">
      <ReportExportBar
        title="Customer Debt Report"
        filename={`customer-debt-${asOfDate}`}
        columns={exportColumns}
        rows={rows}
        extra={
          <Input
            label="As of date"
            type="date"
            value={asOfDate}
            onChange={(e) => setAsOfDate(e.target.value)}
            wrapClassName="w-44"
          />
        }
      />

      <StatCard
        label="Total receivable"
        value={
          report.loading ? "…" : formatMoney(report.data?.grand_total ?? 0)
        }
        icon={Wallet}
        tone="blue"
        hint={`${rows.length} customer${rows.length === 1 ? "" : "s"} with a balance`}
      />

      <Card>
        <CardHeader
          title="Customer debt"
          description="What every customer currently owes, bucketed by days overdue."
        />
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(r) => r.customer.id}
          loading={report.loading}
          error={report.error}
          emptyTitle="No outstanding customer debt"
        />
      </Card>
    </div>
  );
}
