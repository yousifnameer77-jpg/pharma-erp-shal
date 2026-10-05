"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { api } from "@/lib/api";
import { useSimpleList } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import {
  EntityFormModal,
  type FormFieldSchema,
  type FormValue,
} from "@/components/forms/entity-form-modal";
import { useToast } from "@/components/ui/toast";
import type { Category } from "@/lib/types";

export function CategoriesTab() {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.productsManage);
  const toast = useToast();

  const [editing, setEditing] = useState<Category | "new" | null>(null);
  const categories = useSimpleList<Category>("/v1/categories");

  const fields: FormFieldSchema[] = [
    { name: "name", label: "Category name", required: true, colSpan: 2 },
    {
      name: "parent_id",
      label: "Parent category",
      type: "select",
      options: categories.data
        .filter((c) => editing === "new" || c.id !== (editing as Category)?.id)
        .map((c) => ({ value: c.id, label: c.name })),
    },
    { name: "description", label: "Description", type: "textarea", colSpan: 2 },
  ];

  function initialValues(c: Category | "new"): Record<string, FormValue> {
    if (c === "new") return {};
    return {
      name: c.name,
      parent_id: c.parent_id ?? "",
      description: c.description ?? "",
    };
  }

  async function handleSubmit(values: Record<string, FormValue>) {
    const payload = { ...values, parent_id: values.parent_id || null };
    if (editing === "new") {
      await api.post("/v1/categories", payload);
      toast.success("Category created.");
    } else if (editing) {
      await api.put(`/v1/categories/${editing.id}`, payload);
      toast.success("Category updated.");
    }
    categories.refetch();
  }

  async function handleDelete(c: Category) {
    if (!confirm(`Delete category "${c.name}"? This cannot be undone.`)) return;
    try {
      await api.del(`/v1/categories/${c.id}`);
      toast.success("Category deleted.");
      categories.refetch();
    } catch {
      toast.error(
        "Could not delete this category — it may still have products assigned to it.",
      );
    }
  }

  const columns: Column<Category>[] = [
    {
      key: "name",
      header: "Name",
      render: (c) => (
        <span className="font-medium text-slate-900">{c.name}</span>
      ),
    },
    { key: "parent", header: "Parent", render: (c) => c.parent?.name ?? "—" },
    {
      key: "products",
      header: "Products",
      render: (c) => c.products_count ?? 0,
    },
    {
      key: "description",
      header: "Description",
      render: (c) => c.description ?? "—",
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            className: "text-right",
            render: (c: Category) => (
              <div className="flex justify-end gap-3">
                <button
                  onClick={() => setEditing(c)}
                  className="text-xs font-medium text-brand-600 hover:text-brand-700"
                >
                  Edit
                </button>
                <button
                  onClick={() => handleDelete(c)}
                  className="text-xs font-medium text-red-600 hover:text-red-700"
                >
                  Delete
                </button>
              </div>
            ),
          } satisfies Column<Category>,
        ]
      : []),
  ];

  return (
    <Card>
      <div className="flex items-center justify-end border-b border-slate-100 p-4">
        {canManage && (
          <Button size="sm" onClick={() => setEditing("new")}>
            <Plus className="h-4 w-4" /> Add category
          </Button>
        )}
      </div>
      <DataTable
        columns={columns}
        rows={categories.data}
        rowKey={(c) => c.id}
        loading={categories.loading}
        error={categories.error}
        emptyTitle="No categories yet"
      />
      {editing && (
        <EntityFormModal
          key={editing === "new" ? "new" : editing.id}
          open
          onClose={() => setEditing(null)}
          title={editing === "new" ? "Add category" : `Edit ${editing.name}`}
          fields={fields}
          initialValues={initialValues(editing)}
          onSubmit={handleSubmit}
        />
      )}
    </Card>
  );
}
