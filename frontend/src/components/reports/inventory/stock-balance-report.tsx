"use client";

import { useState } from "react";
import { usePaginatedResource, useSimpleList } from "@/lib/hooks";
import { formatNumber } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Select, Checkbox } from "@/components/ui/field";
import {
  ReportExportBar,
  type ExportColumn,
} from "@/components/reports/export-bar";
import type { StockRow, Warehouse } from "@/lib/types";

export function StockBalanceReport() {
  const [warehouseId, setWarehouseId] = useState("");
  const [onlyAvailable, setOnlyAvailable] = useState(true);

  const warehouses = useSimpleList<Warehouse>("/v1/warehouses");
  const stock = usePaginatedResource<StockRow>("/v1/stock", {
    warehouse_id: warehouseId || undefined,
    only_available: onlyAvailable || undefined,
    per_page: 500,
  });

  const columns: Column<StockRow>[] = [
    {
      key: "product",
      header: "Product",
      render: (s) => (
        <span className="font-medium text-slate-900">
          {s.batch?.product?.name ?? "—"}
        </span>
      ),
    },
    {
      key: "batch",
      header: "Batch #",
      render: (s) => s.batch?.batch_number ?? "—",
    },
    {
      key: "warehouse",
      header: "Warehouse",
      render: (s) => s.warehouse?.name ?? "—",
    },
    {
      key: "onhand",
      header: "On hand",
      render: (s) => formatNumber(s.quantity_on_hand),
      className: "text-right",
    },
    {
      key: "reserved",
      header: "Reserved",
      render: (s) => formatNumber(s.reserved_quantity),
      className: "text-right",
    },
    {
      key: "available",
      header: "Available",
      render: (s) => formatNumber(s.available_quantity),
      className: "text-right",
    },
  ];

  const exportColumns: ExportColumn<StockRow>[] = [
    { header: "Product", value: (s) => s.batch?.product?.name ?? "" },
    { header: "Batch #", value: (s) => s.batch?.batch_number ?? "" },
    { header: "Warehouse", value: (s) => s.warehouse?.name ?? "" },
    { header: "On hand", value: (s) => formatNumber(s.quantity_on_hand) },
    { header: "Reserved", value: (s) => formatNumber(s.reserved_quantity) },
    { header: "Available", value: (s) => formatNumber(s.available_quantity) },
  ];

  return (
    <div className="flex flex-col gap-4">
      <ReportExportBar
        title="Stock Balance Report"
        filename="stock-balance"
        columns={exportColumns}
        rows={stock.data}
        extra={
          <>
            <Select
              label="Warehouse"
              value={warehouseId}
              onChange={(e) => setWarehouseId(e.target.value)}
              placeholder="All warehouses"
              wrapClassName="w-52"
            >
              {warehouses.data.map((w) => (
                <option key={w.id} value={w.id}>
                  {w.name}
                </option>
              ))}
            </Select>
            <div className="pb-2">
              <Checkbox
                label="Available only"
                checked={onlyAvailable}
                onChange={(e) => setOnlyAvailable(e.target.checked)}
              />
            </div>
          </>
        }
      />

      <Card>
        <CardHeader
          title="Stock balance"
          description="Current on-hand quantity per warehouse and batch."
          action={
            stock.meta && (
              <span className="text-xs text-slate-400">
                {stock.meta.total} record{stock.meta.total === 1 ? "" : "s"}
              </span>
            )
          }
        />
        <DataTable
          columns={columns}
          rows={stock.data}
          rowKey={(s) => `${s.warehouse_id}-${s.batch_id}`}
          loading={stock.loading}
          error={stock.error}
          emptyTitle="No stock recorded yet"
        />
      </Card>
    </div>
  );
}
