"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { api } from "@/lib/api";
import { usePaginatedResource, useSimpleList } from "@/lib/hooks";
import { formatMoney, formatNumber } from "@/lib/format";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Pagination } from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";
import { Input, Select, Checkbox } from "@/components/ui/field";
import { Badge } from "@/components/ui/badge";
import {
  EntityFormModal,
  type FormFieldSchema,
  type FormValue,
} from "@/components/forms/entity-form-modal";
import { useToast } from "@/components/ui/toast";
import type { Category, Manufacturer, Product } from "@/lib/types";

export function ProductsTab() {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.productsManage);
  const toast = useToast();

  const [search, setSearch] = useState("");
  const [categoryId, setCategoryId] = useState("");
  const [lowStockOnly, setLowStockOnly] = useState(false);
  const [editing, setEditing] = useState<Product | "new" | null>(null);

  const categories = useSimpleList<Category>("/v1/categories");
  const manufacturers = usePaginatedResource<Manufacturer>(
    "/v1/manufacturers",
    { per_page: 200 },
  );

  const list = usePaginatedResource<Product>("/v1/products", {
    search: search || undefined,
    category_id: categoryId || undefined,
  });
  const lowStock = useSimpleList<Product>(
    "/v1/products/low-stock",
    {},
    lowStockOnly,
  );

  const rows = lowStockOnly ? lowStock.data : list.data;
  const loading = lowStockOnly ? lowStock.loading : list.loading;
  const error = lowStockOnly ? lowStock.error : list.error;

  const fields: FormFieldSchema[] = [
    { name: "name", label: "Product name", required: true, colSpan: 2 },
    { name: "generic_name", label: "المادة الفعالة (Active Ingredient)" },
    { name: "code", label: "Code", required: true },
    { name: "barcode", label: "Barcode" },
    {
      name: "manufacturer_id",
      label: "Manufacturer",
      type: "select",
      required: true,
      options: manufacturers.data.map((m) => ({ value: m.id, label: m.name })),
    },
    {
      name: "category_id",
      label: "Category",
      type: "select",
      options: categories.data.map((c) => ({ value: c.id, label: c.name })),
    },
    { name: "form", label: "Form", placeholder: "tablet, syrup, injection…" },
    { name: "strength", label: "Strength", placeholder: "500mg" },
    {
      name: "base_unit",
      label: "Base unit",
      required: true,
      placeholder: "box, bottle, strip…",
    },
    { name: "pack_size", label: "Pack size", type: "number", min: 1 },
    {
      name: "purchase_price",
      label: "Purchase price",
      type: "number",
      step: "0.001",
      min: 0,
    },
    {
      name: "sale_price",
      label: "Sale price",
      type: "number",
      step: "0.001",
      min: 0,
    },
    {
      name: "min_stock_level",
      label: "Min stock level",
      type: "number",
      step: "0.001",
      min: 0,
    },
    {
      name: "reorder_point",
      label: "Reorder point",
      type: "number",
      step: "0.001",
      min: 0,
    },
    {
      name: "tax_rate",
      label: "Tax rate (%)",
      type: "number",
      step: "0.01",
      min: 0,
    },
    {
      name: "is_controlled_substance",
      label: "Controlled substance",
      type: "checkbox",
      colSpan: 1,
    },
    {
      name: "requires_prescription",
      label: "Requires prescription",
      type: "checkbox",
      colSpan: 1,
    },
    { name: "is_active", label: "Active", type: "checkbox", colSpan: 1 },
  ];

  function initialValues(p: Product | "new"): Record<string, FormValue> {
    if (p === "new") {
      return {
        is_active: true,
        is_controlled_substance: false,
        requires_prescription: false,
      };
    }
    return {
      name: p.name,
      generic_name: p.generic_name ?? "",
      code: p.code,
      barcode: p.barcode ?? "",
      manufacturer_id: p.manufacturer_id,
      category_id: p.category_id ?? "",
      form: p.form ?? "",
      strength: p.strength ?? "",
      base_unit: p.base_unit,
      pack_size: p.pack_size ?? "",
      purchase_price: p.purchase_price ?? "",
      sale_price: p.sale_price ?? "",
      min_stock_level: p.min_stock_level ?? "",
      reorder_point: p.reorder_point ?? "",
      tax_rate: p.tax_rate ?? "",
      is_controlled_substance: p.is_controlled_substance,
      requires_prescription: p.requires_prescription,
      is_active: p.is_active,
    };
  }

  async function handleSubmit(values: Record<string, FormValue>) {
    const payload = { ...values };
    if (payload.category_id === "") payload.category_id = null;
    if (editing === "new") {
      await api.post("/v1/products", payload);
      toast.success("Product created.");
    } else if (editing) {
      await api.put(`/v1/products/${editing.id}`, payload);
      toast.success("Product updated.");
    }
    list.refetch();
    lowStock.refetch();
  }

  const columns: Column<Product>[] = [
    {
      key: "name",
      header: "Product",
      render: (p) => (
        <div>
          <p className="font-semibold text-slate-900">{p.name}</p>
          {p.generic_name && (
            <p className="text-[11px] font-medium text-emerald-700">
              المادة الفعالة: {p.generic_name}
            </p>
          )}
          <p className="text-xs text-slate-400">
            {p.code}
            {p.strength ? ` · ${p.strength}` : ""}
          </p>
        </div>
      ),
    },
    {
      key: "category",
      header: "Category",
      render: (p) => p.category?.name ?? "—",
    },
    {
      key: "manufacturer",
      header: "Manufacturer",
      render: (p) => p.manufacturer?.name ?? "—",
    },
    {
      key: "stock",
      header: "Stock",
      render: (p) => (
        <span
          className={p.is_below_min_stock ? "font-medium text-amber-600" : ""}
        >
          {formatNumber(p.total_stock, 0)} {p.base_unit}
        </span>
      ),
    },
    {
      key: "sale_price",
      header: "Sale price",
      render: (p) => formatMoney(p.sale_price),
      className: "text-right",
    },
    {
      key: "status",
      header: "Status",
      render: (p) => (
        <Badge tone={p.is_active ? "emerald" : "slate"}>
          {p.is_active ? "Active" : "Inactive"}
        </Badge>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            render: (p: Product) => (
              <button
                onClick={(e) => {
                  e.stopPropagation();
                  setEditing(p);
                }}
                className="text-xs font-medium text-brand-600 hover:text-brand-700"
              >
                Edit
              </button>
            ),
            className: "text-right",
          } satisfies Column<Product>,
        ]
      : []),
  ];

  return (
    <Card>
      <div className="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-1 flex-wrap items-center gap-2">
          <Input
            placeholder="Search products…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full sm:w-56"
            wrapClassName="flex-1 sm:flex-none"
          />
          <Select
            value={categoryId}
            onChange={(e) => setCategoryId(e.target.value)}
            placeholder="All categories"
            className="w-full sm:w-44"
            wrapClassName="flex-1 sm:flex-none"
          >
            {categories.data.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </Select>
          <Checkbox
            label="Low stock only"
            checked={lowStockOnly}
            onChange={(e) => setLowStockOnly(e.target.checked)}
          />
        </div>
        {canManage && (
          <Button size="sm" onClick={() => setEditing("new")}>
            <Plus className="h-4 w-4" /> Add product
          </Button>
        )}
      </div>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(p) => p.id}
        loading={loading}
        error={error}
        emptyTitle="No products found"
        emptyDescription="Try adjusting your search or filters."
      />
      {!lowStockOnly && (
        <Pagination
          meta={list.meta}
          page={list.page}
          onPageChange={list.setPage}
        />
      )}

      {editing && (
        <EntityFormModal
          key={editing === "new" ? "new" : editing.id}
          open
          onClose={() => setEditing(null)}
          title={editing === "new" ? "Add product" : `Edit ${editing.name}`}
          fields={fields}
          initialValues={initialValues(editing)}
          onSubmit={handleSubmit}
          size="lg"
        />
      )}
    </Card>
  );
}
