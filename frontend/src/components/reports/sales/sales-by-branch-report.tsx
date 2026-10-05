"use client";

import { useMemo, useState } from "react";
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { useApiResource } from "@/lib/hooks";
import { formatMoney } from "@/lib/format";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Input } from "@/components/ui/field";
import {
  ReportExportBar,
  type ExportColumn,
} from "@/components/reports/export-bar";
import type {
  SalesByBranchReport as SalesByBranchReportData,
  SalesByBranchRow,
} from "@/lib/types";

export function SalesByBranchReport({ companyId }: { companyId: string }) {
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  const report = useApiResource<SalesByBranchReportData>(
    "/v1/reports/sales/by-branch",
    {
      company_id: companyId,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
    },
  );
  const rows = report.data?.branches ?? [];
  const grandTotal = report.data?.total_revenue ?? 0;

  const chartData = useMemo(
    () =>
      rows.map((r) => ({
        name: r.branch?.name ?? "Unknown",
        revenue: r.total_revenue,
      })),
    [rows],
  );

  const columns: Column<SalesByBranchRow>[] = [
    {
      key: "branch",
      header: "Branch",
      render: (r) => (
        <span className="font-medium text-slate-900">
          {r.branch?.name ?? "Unknown"}
        </span>
      ),
    },
    {
      key: "count",
      header: "Invoices",
      render: (r) => r.invoice_count,
      className: "text-right",
    },
    {
      key: "revenue",
      header: "Revenue",
      render: (r) => formatMoney(r.total_revenue),
      className: "text-right",
    },
    {
      key: "share",
      header: "% of total",
      render: (r) =>
        `${grandTotal > 0 ? ((r.total_revenue / grandTotal) * 100).toFixed(1) : "0.0"}%`,
      className: "text-right",
    },
  ];

  const exportColumns: ExportColumn<SalesByBranchRow>[] = [
    { header: "Branch", value: (r) => r.branch?.name ?? "Unknown" },
    { header: "Invoices", value: (r) => String(r.invoice_count) },
    { header: "Revenue", value: (r) => formatMoney(r.total_revenue) },
    {
      header: "% of total",
      value: (r) =>
        `${grandTotal > 0 ? ((r.total_revenue / grandTotal) * 100).toFixed(1) : "0.0"}%`,
    },
  ];

  const rangeLabel = `${dateFrom || "all-time"}_to_${dateTo || "now"}`;

  return (
    <div className="flex flex-col gap-4">
      <ReportExportBar
        title="Sales by Branch"
        filename={`sales-by-branch-${rangeLabel}`}
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

      <Card>
        <CardHeader
          title="Revenue by branch"
          description="Posted sales revenue, compared across branches."
        />
        <CardBody>
          {report.loading ? (
            <div className="h-56 animate-pulse rounded-lg bg-slate-50" />
          ) : chartData.length === 0 ? (
            <p className="py-10 text-center text-sm text-slate-400">
              No sales in this range.
            </p>
          ) : (
            <div className="h-56">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={chartData} margin={{ left: -12 }}>
                  <CartesianGrid
                    strokeDasharray="3 3"
                    vertical={false}
                    stroke="#e2e8f0"
                  />
                  <XAxis
                    dataKey="name"
                    tick={{ fontSize: 12, fill: "#64748b" }}
                    axisLine={false}
                    tickLine={false}
                  />
                  <YAxis
                    tick={{ fontSize: 12, fill: "#64748b" }}
                    axisLine={false}
                    tickLine={false}
                  />
                  <Tooltip
                    formatter={(v: number) => formatMoney(v)}
                    contentStyle={{
                      borderRadius: 8,
                      borderColor: "#e2e8f0",
                      fontSize: 12,
                    }}
                  />
                  <Bar dataKey="revenue" fill="#4a6bf5" radius={[6, 6, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader
          title="By branch"
          action={
            <span className="text-sm font-semibold text-slate-900">
              Total {formatMoney(grandTotal)}
            </span>
          }
        />
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(r) => r.branch?.id ?? "unknown"}
          loading={report.loading}
          error={report.error}
          emptyTitle="No sales in this range"
        />
      </Card>
    </div>
  );
}
