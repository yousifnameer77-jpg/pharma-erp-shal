"use client";

import { useMemo, useState } from "react";
import { Receipt, Wallet, TrendingUp } from "lucide-react";
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
import { FieldWrap } from "@/components/ui/field";
import { StatCard } from "@/components/ui/stat-card";
import {
  ReportExportBar,
  type ExportColumn,
} from "@/components/reports/export-bar";
import type {
  MonthlySalesDay,
  MonthlySalesReport as MonthlySalesReportData,
} from "@/lib/types";

function currentMonthValue(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
}

export function MonthlySalesReport({ companyId }: { companyId: string }) {
  const [monthValue, setMonthValue] = useState(currentMonthValue());
  const [year, month] = monthValue.split("-").map(Number);

  const report = useApiResource<MonthlySalesReportData>(
    "/v1/reports/sales/monthly",
    {
      company_id: companyId,
      year,
      month,
    },
  );
  const days = report.data?.days ?? [];

  const chartData = useMemo(
    () =>
      days.map((d) => ({ name: d.date.slice(-2), revenue: d.total_revenue })),
    [days],
  );

  const columns: Column<MonthlySalesDay>[] = [
    {
      key: "date",
      header: "Date",
      render: (d) => (
        <span className="font-medium text-slate-900">{d.date}</span>
      ),
    },
    {
      key: "count",
      header: "Invoices",
      render: (d) => d.invoice_count,
      className: "text-right",
    },
    {
      key: "revenue",
      header: "Revenue",
      render: (d) => formatMoney(d.total_revenue),
      className: "text-right",
    },
  ];

  const exportColumns: ExportColumn<MonthlySalesDay>[] = [
    { header: "Date", value: (d) => d.date },
    { header: "Invoices", value: (d) => String(d.invoice_count) },
    { header: "Revenue", value: (d) => formatMoney(d.total_revenue) },
  ];

  return (
    <div className="flex flex-col gap-4">
      <ReportExportBar
        title={`Monthly Sales — ${monthValue}`}
        filename={`monthly-sales-${monthValue}`}
        columns={exportColumns}
        rows={days}
        extra={
          <FieldWrap label="Month" className="w-44">
            <input
              type="month"
              value={monthValue}
              onChange={(e) => setMonthValue(e.target.value)}
              className="h-9 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
            />
          </FieldWrap>
        }
      />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
          label="Invoices this month"
          value={report.loading ? "…" : (report.data?.invoice_count ?? 0)}
          icon={Receipt}
        />
        <StatCard
          label="Total revenue"
          value={
            report.loading ? "…" : formatMoney(report.data?.total_revenue ?? 0)
          }
          icon={Wallet}
          tone="emerald"
        />
        <StatCard
          label="Average per active day"
          value={
            report.loading
              ? "…"
              : formatMoney(
                  days.length > 0
                    ? (report.data?.total_revenue ?? 0) / days.length
                    : 0,
                )
          }
          icon={TrendingUp}
          tone="blue"
        />
      </div>

      <Card>
        <CardHeader
          title="Daily revenue trend"
          description="Days with at least one posted sale."
        />
        <CardBody>
          {report.loading ? (
            <div className="h-56 animate-pulse rounded-lg bg-slate-50" />
          ) : chartData.length === 0 ? (
            <p className="py-10 text-center text-sm text-slate-400">
              No sales this month.
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
        <CardHeader title="Day by day" />
        <DataTable
          columns={columns}
          rows={days}
          rowKey={(d) => d.date}
          loading={report.loading}
          error={report.error}
          emptyTitle="No sales this month"
        />
      </Card>
    </div>
  );
}
