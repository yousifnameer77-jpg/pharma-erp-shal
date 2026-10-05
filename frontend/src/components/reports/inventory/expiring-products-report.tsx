"use client";

import { useState } from "react";
import { usePaginatedResource } from "@/lib/hooks";
import { formatDate, formatNumber } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Select } from "@/components/ui/field";
import { StatCard } from "@/components/ui/stat-card";
import { CalendarClock } from "lucide-react";
import {
  ReportExportBar,
  type ExportColumn,
} from "@/components/reports/export-bar";
import type { Batch } from "@/lib/types";

const WINDOWS = [
  { value: "7", label: "Next 7 days" },
  { value: "30", label: "Next 30 days" },
  { value: "60", label: "Next 60 days" },
  { value: "90", label: "Next 90 days" },
];

export function ExpiringProductsReport() {
  const [days, setDays] = useState("30");

  const batches = usePaginatedResource<Batch>("/v1/batches", {
    expiring_within_days: Number(days),
    per_page: 500,
  });

  const columns: Column<Batch>[] = [
    {
      key: "batch",
      header: "Batch #",
      render: (b) => (
        <span className="font-medium text-slate-900">{b.batch_number}</span>
      ),
    },
    {
      key: "product",
      header: "Product",
      render: (b) => b.product?.name ?? b.product_id,
    },
    {
      key: "supplier",
      header: "Supplier",
      render: (b) => b.supplier?.name ?? "—",
    },
    {
      key: "qty",
      header: "Qty on hand",
      render: (b) => formatNumber(b.total_quantity),
      className: "text-right",
    },
    {
      key: "expiry",
      header: "Expiry date",
      render: (b) => formatDate(b.expiry_date),
      className: "text-right",
    },
  ];

  const exportColumns: ExportColumn<Batch>[] = [
    { header: "Batch #", value: (b) => b.batch_number },
    { header: "Product", value: (b) => b.product?.name ?? b.product_id },
    { header: "Supplier", value: (b) => b.supplier?.name ?? "" },
    { header: "Qty on hand", value: (b) => formatNumber(b.total_quantity) },
    { header: "Expiry date", value: (b) => formatDate(b.expiry_date) },
  ];

  return (
    <div className="flex flex-col gap-4">
      <ReportExportBar
        title={`Expiring Products (${WINDOWS.find((w) => w.value === days)?.label ?? ""})`}
        filename={`expiring-products-${days}d`}
        columns={exportColumns}
        rows={batches.data}
        extra={
          <Select
            label="Window"
            value={days}
            onChange={(e) => setDays(e.target.value)}
            wrapClassName="w-44"
          >
            {WINDOWS.map((w) => (
              <option key={w.value} value={w.value}>
                {w.label}
              </option>
            ))}
          </Select>
        }
      />

      <StatCard
        label="Batches expiring soon"
        value={
          batches.loading ? "…" : (batches.meta?.total ?? batches.data.length)
        }
        icon={CalendarClock}
        tone={batches.data.length > 0 ? "amber" : "slate"}
      />

      <Card>
        <CardHeader
          title="Batches expiring soon"
          description="Stocked batches approaching their expiry date, soonest first."
        />
        <DataTable
          columns={columns}
          rows={batches.data}
          rowKey={(b) => b.id}
          loading={batches.loading}
          error={batches.error}
          emptyTitle="Nothing expiring in this window"
        />
      </Card>
    </div>
  );
}
