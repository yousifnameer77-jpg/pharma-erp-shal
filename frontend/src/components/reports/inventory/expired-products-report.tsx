"use client";

import { usePaginatedResource } from "@/lib/hooks";
import { formatDate, formatNumber } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { StatCard } from "@/components/ui/stat-card";
import { AlertOctagon } from "lucide-react";
import {
  ReportExportBar,
  type ExportColumn,
} from "@/components/reports/export-bar";
import type { Batch } from "@/lib/types";

export function ExpiredProductsReport() {
  const batches = usePaginatedResource<Batch>("/v1/batches", {
    expired: true,
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
      header: "Expired on",
      render: (b) => formatDate(b.expiry_date),
      className: "text-right",
    },
  ];

  const exportColumns: ExportColumn<Batch>[] = [
    { header: "Batch #", value: (b) => b.batch_number },
    { header: "Product", value: (b) => b.product?.name ?? b.product_id },
    { header: "Supplier", value: (b) => b.supplier?.name ?? "" },
    { header: "Qty on hand", value: (b) => formatNumber(b.total_quantity) },
    { header: "Expired on", value: (b) => formatDate(b.expiry_date) },
  ];

  return (
    <div className="flex flex-col gap-4">
      <ReportExportBar
        title="Expired Products Report"
        filename="expired-products"
        columns={exportColumns}
        rows={batches.data}
      />

      <StatCard
        label="Expired batches still on hand"
        value={
          batches.loading ? "…" : (batches.meta?.total ?? batches.data.length)
        }
        icon={AlertOctagon}
        tone={batches.data.length > 0 ? "red" : "slate"}
        hint={
          batches.data.length > 0
            ? "Remove from sellable stock — see Inventory Adjustments"
            : "Nothing expired"
        }
      />

      <Card>
        <CardHeader
          title="Expired batches"
          description="Batches past their expiry date that still carry quantity on hand."
        />
        <DataTable
          columns={columns}
          rows={batches.data}
          rowKey={(b) => b.id}
          loading={batches.loading}
          error={batches.error}
          emptyTitle="No expired batches"
        />
      </Card>
    </div>
  );
}
