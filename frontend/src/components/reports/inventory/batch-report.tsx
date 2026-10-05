"use client";

import { useState } from "react";
import { usePaginatedResource, useSimpleList } from "@/lib/hooks";
import { formatDate, formatNumber } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Select } from "@/components/ui/field";
import { Badge } from "@/components/ui/badge";
import {
  ReportExportBar,
  type ExportColumn,
} from "@/components/reports/export-bar";
import type { Batch, Product, Supplier } from "@/lib/types";

export function BatchReport({ companyId }: { companyId: string }) {
  const [productId, setProductId] = useState("");
  const [supplierId, setSupplierId] = useState("");

  const products = useSimpleList<Product>("/v1/products", { per_page: 500 });
  const suppliers = useSimpleList<Supplier>("/v1/suppliers", {
    company_id: companyId,
    per_page: 500,
  });
  const batches = usePaginatedResource<Batch>("/v1/batches", {
    product_id: productId || undefined,
    supplier_id: supplierId || undefined,
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
      key: "manufacture",
      header: "Manufactured",
      render: (b) => formatDate(b.manufacture_date),
    },
    {
      key: "expiry",
      header: "Expiry date",
      render: (b) => formatDate(b.expiry_date),
    },
    {
      key: "qty",
      header: "Qty on hand",
      render: (b) => formatNumber(b.total_quantity),
      className: "text-right",
    },
    {
      key: "status",
      header: "Status",
      render: (b) => (
        <Badge tone={b.is_expired ? "red" : "emerald"}>
          {b.is_expired ? "Expired" : "Active"}
        </Badge>
      ),
    },
  ];

  const exportColumns: ExportColumn<Batch>[] = [
    { header: "Batch #", value: (b) => b.batch_number },
    { header: "Product", value: (b) => b.product?.name ?? b.product_id },
    { header: "Supplier", value: (b) => b.supplier?.name ?? "" },
    { header: "Manufactured", value: (b) => formatDate(b.manufacture_date) },
    { header: "Expiry date", value: (b) => formatDate(b.expiry_date) },
    { header: "Qty on hand", value: (b) => formatNumber(b.total_quantity) },
    { header: "Status", value: (b) => (b.is_expired ? "Expired" : "Active") },
  ];

  return (
    <div className="flex flex-col gap-4">
      <ReportExportBar
        title="Batch Report"
        filename="batch-report"
        columns={exportColumns}
        rows={batches.data}
        extra={
          <>
            <Select
              label="Product"
              value={productId}
              onChange={(e) => setProductId(e.target.value)}
              placeholder="All products"
              wrapClassName="w-52"
            >
              {products.data.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </Select>
            <Select
              label="Supplier"
              value={supplierId}
              onChange={(e) => setSupplierId(e.target.value)}
              placeholder="All suppliers"
              wrapClassName="w-52"
            >
              {suppliers.data.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </>
        }
      />

      <Card>
        <CardHeader
          title="All batches"
          description="Every received batch, its expiry status and remaining quantity."
          action={
            batches.meta && (
              <span className="text-xs text-slate-400">
                {batches.meta.total} batch{batches.meta.total === 1 ? "" : "es"}
              </span>
            )
          }
        />
        <DataTable
          columns={columns}
          rows={batches.data}
          rowKey={(b) => b.id}
          loading={batches.loading}
          error={batches.error}
          emptyTitle="No batches found"
        />
      </Card>
    </div>
  );
}
