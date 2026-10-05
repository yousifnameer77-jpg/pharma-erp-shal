"use client";

import { useState } from "react";
import { Receipt, Percent, Tag, Wallet } from "lucide-react";
import { useApiResource } from "@/lib/hooks";
import { formatMoney, todayIso } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Input } from "@/components/ui/field";
import { StatCard } from "@/components/ui/stat-card";
import { StatusBadge } from "@/components/ui/badge";
import {
  ReportExportBar,
  type ExportColumn,
} from "@/components/reports/export-bar";
import type {
  DailySalesReport as DailySalesReportData,
  SalesInvoice,
} from "@/lib/types";

export function DailySalesReport({ companyId }: { companyId: string }) {
  const [date, setDate] = useState(todayIso());

  const report = useApiResource<DailySalesReportData>(
    "/v1/reports/sales/daily",
    {
      company_id: companyId,
      date,
    },
  );
  const invoices = report.data?.invoices ?? [];

  const columns: Column<SalesInvoice>[] = [
    {
      key: "number",
      header: "Invoice #",
      render: (i) => (
        <span className="font-medium text-slate-900">{i.invoice_number}</span>
      ),
    },
    {
      key: "customer",
      header: "Customer",
      render: (i) => i.customer?.name ?? "—",
    },
    {
      key: "subtotal",
      header: "Subtotal",
      render: (i) => formatMoney(i.subtotal),
      className: "text-right",
    },
    {
      key: "tax",
      header: "Tax",
      render: (i) => formatMoney(i.tax_amount),
      className: "text-right",
    },
    {
      key: "total",
      header: "Total",
      render: (i) => (
        <span className="font-semibold text-slate-900">
          {formatMoney(i.total_amount)}
        </span>
      ),
      className: "text-right",
    },
    {
      key: "status",
      header: "Status",
      render: (i) => <StatusBadge status={i.status} />,
    },
  ];

  const exportColumns: ExportColumn<SalesInvoice>[] = [
    { header: "Invoice #", value: (i) => i.invoice_number },
    { header: "Customer", value: (i) => i.customer?.name ?? "" },
    { header: "Subtotal", value: (i) => formatMoney(i.subtotal) },
    { header: "Discount", value: (i) => formatMoney(i.discount_amount) },
    { header: "Tax", value: (i) => formatMoney(i.tax_amount) },
    { header: "Total", value: (i) => formatMoney(i.total_amount) },
    { header: "Status", value: (i) => i.status },
  ];

  return (
    <div className="flex flex-col gap-4">
      <ReportExportBar
        title={`Daily Sales — ${date}`}
        filename={`daily-sales-${date}`}
        columns={exportColumns}
        rows={invoices}
        extra={
          <Input
            label="Date"
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            wrapClassName="w-44"
          />
        }
      />

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <StatCard
          label="Invoices"
          value={report.loading ? "…" : (report.data?.invoice_count ?? 0)}
          icon={Receipt}
        />
        <StatCard
          label="Subtotal"
          value={
            report.loading ? "…" : formatMoney(report.data?.total_subtotal ?? 0)
          }
          icon={Tag}
          tone="slate"
        />
        <StatCard
          label="Tax collected"
          value={
            report.loading ? "…" : formatMoney(report.data?.total_tax ?? 0)
          }
          icon={Percent}
          tone="blue"
        />
        <StatCard
          label="Total revenue"
          value={
            report.loading ? "…" : formatMoney(report.data?.total_revenue ?? 0)
          }
          icon={Wallet}
          tone="emerald"
        />
      </div>

      <Card>
        <CardHeader
          title="Invoices"
          description="Every posted, partially paid or paid sale on this date."
        />
        <DataTable
          columns={columns}
          rows={invoices}
          rowKey={(i) => i.id}
          loading={report.loading}
          error={report.error}
          emptyTitle="No sales on this date"
        />
      </Card>
    </div>
  );
}
