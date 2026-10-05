"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { api } from "@/lib/api";
import { usePaginatedResource } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Pagination } from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/field";
import { Badge } from "@/components/ui/badge";
import {
  EntityFormModal,
  type FormFieldSchema,
  type FormValue,
} from "@/components/forms/entity-form-modal";
import { useToast } from "@/components/ui/toast";
import type { Manufacturer } from "@/lib/types";

export function ManufacturersTab() {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.productsManage);
  const toast = useToast();

  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState<Manufacturer | "new" | null>(null);

  const list = usePaginatedResource<Manufacturer>("/v1/manufacturers", {
    search: search || undefined,
  });

  const fields: FormFieldSchema[] = [
    { name: "name", label: "Manufacturer name", required: true, colSpan: 2 },
    { name: "country", label: "Country" },
    { name: "is_active", label: "Active", type: "checkbox" },
  ];

  function initialValues(m: Manufacturer | "new"): Record<string, FormValue> {
    if (m === "new") return { is_active: true };
    return { name: m.name, country: m.country ?? "", is_active: m.is_active };
  }

  async function handleSubmit(values: Record<string, FormValue>) {
    if (editing === "new") {
      await api.post("/v1/manufacturers", values);
      toast.success("Manufacturer created.");
    } else if (editing) {
      await api.put(`/v1/manufacturers/${editing.id}`, values);
      toast.success("Manufacturer updated.");
    }
    list.refetch();
  }

  async function toggleActive(m: Manufacturer) {
    if (m.is_active) {
      await api.del(`/v1/manufacturers/${m.id}`);
      toast.success("Manufacturer deactivated.");
    } else {
      await api.put(`/v1/manufacturers/${m.id}`, { is_active: true });
      toast.success("Manufacturer activated.");
    }
    list.refetch();
  }

  const columns: Column<Manufacturer>[] = [
    {
      key: "name",
      header: "Name",
      render: (m) => (
        <span className="font-medium text-slate-900">{m.name}</span>
      ),
    },
    { key: "country", header: "Country", render: (m) => m.country ?? "—" },
    {
      key: "products",
      header: "Products",
      render: (m) => m.products_count ?? 0,
    },
    {
      key: "status",
      header: "Status",
      render: (m) => (
        <Badge tone={m.is_active ? "emerald" : "slate"}>
          {m.is_active ? "Active" : "Inactive"}
        </Badge>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            className: "text-right",
            render: (m: Manufacturer) => (
              <div className="flex justify-end gap-3">
                <button
                  onClick={() => setEditing(m)}
                  className="text-xs font-medium text-brand-600 hover:text-brand-700"
                >
                  Edit
                </button>
                <button
                  onClick={() => toggleActive(m)}
                  className="text-xs font-medium text-slate-500 hover:text-slate-700"
                >
                  {m.is_active ? "Deactivate" : "Activate"}
                </button>
              </div>
            ),
          } satisfies Column<Manufacturer>,
        ]
      : []),
  ];

  return (
    <Card>
      <div className="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
        <Input
          placeholder="Search manufacturers…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="w-full sm:w-64"
        />
        {canManage && (
          <Button size="sm" onClick={() => setEditing("new")}>
            <Plus className="h-4 w-4" /> Add manufacturer
          </Button>
        )}
      </div>
      <DataTable
        columns={columns}
        rows={list.data}
        rowKey={(m) => m.id}
        loading={list.loading}
        error={list.error}
        emptyTitle="No manufacturers yet"
      />
      <Pagination
        meta={list.meta}
        page={list.page}
        onPageChange={list.setPage}
      />
      {editing && (
        <EntityFormModal
          key={editing === "new" ? "new" : editing.id}
          open
          onClose={() => setEditing(null)}
          title={
            editing === "new" ? "Add manufacturer" : `Edit ${editing.name}`
          }
          fields={fields}
          initialValues={initialValues(editing)}
          onSubmit={handleSubmit}
        />
      )}
    </Card>
  );
}
