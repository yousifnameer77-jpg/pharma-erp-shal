"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { api } from "@/lib/api";
import { useSimpleList } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { titleCase } from "@/lib/format";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  EntityFormModal,
  type FormFieldSchema,
  type FormValue,
} from "@/components/forms/entity-form-modal";
import { useToast } from "@/components/ui/toast";
import type { ChartOfAccount } from "@/lib/types";

const TYPE_OPTIONS = ["asset", "liability", "equity", "revenue", "expense"].map(
  (value) => ({ value, label: titleCase(value) }),
);
const CATEGORY_OPTIONS = ["cash", "bank", "receivable", "payable"].map(
  (value) => ({ value, label: titleCase(value) }),
);

export function ChartOfAccountsTab({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.financeManage);
  const toast = useToast();

  const [editing, setEditing] = useState<ChartOfAccount | "new" | null>(null);
  const accounts = useSimpleList<ChartOfAccount>("/v1/chart-of-accounts", {
    company_id: companyId,
  });
  const parentOptions = accounts.data.map((a) => ({
    value: a.id,
    label: `${a.code} — ${a.name}`,
  }));

  const fields: FormFieldSchema[] = [
    {
      name: "code",
      label: "Code",
      required: true,
      hidden: () => editing !== "new",
    },
    { name: "name", label: "Name", required: true },
    {
      name: "type",
      label: "Type",
      type: "select",
      required: true,
      options: TYPE_OPTIONS,
      hidden: () => editing !== "new",
    },
    {
      name: "category",
      label: "Category (optional)",
      type: "select",
      options: CATEGORY_OPTIONS,
      hint: "Only for cash, bank, receivable or payable accounts.",
    },
    {
      name: "parent_id",
      label: "Parent account (optional)",
      type: "select",
      options: parentOptions.filter(
        (o) => !editing || editing === "new" || o.value !== editing.id,
      ),
    },
    { name: "is_active", label: "Active", type: "checkbox" },
  ];

  function initialValues(a: ChartOfAccount | "new"): Record<string, FormValue> {
    if (a === "new") return { is_active: true };
    return {
      code: a.code,
      name: a.name,
      type: a.type,
      category: a.category ?? "",
      parent_id: a.parent_id ?? "",
      is_active: a.is_active,
    };
  }

  async function handleSubmit(values: Record<string, FormValue>) {
    if (editing === "new") {
      await api.post("/v1/chart-of-accounts", {
        ...values,
        company_id: companyId,
      });
      toast.success("Account created.");
    } else if (editing) {
      const { code: _code, type: _type, ...rest } = values;
      await api.put(`/v1/chart-of-accounts/${editing.id}`, rest);
      toast.success("Account updated.");
    }
    accounts.refetch();
  }

  async function toggleActive(a: ChartOfAccount) {
    await api.put(`/v1/chart-of-accounts/${a.id}`, { is_active: !a.is_active });
    toast.success(a.is_active ? "Account deactivated." : "Account activated.");
    accounts.refetch();
  }

  const accountsById = new Map(accounts.data.map((a) => [a.id, a]));

  const columns: Column<ChartOfAccount>[] = [
    {
      key: "code",
      header: "Code",
      render: (a) => (
        <span className="font-mono text-xs text-slate-500">{a.code}</span>
      ),
    },
    {
      key: "name",
      header: "Name",
      render: (a) => (
        <span className="font-medium text-slate-900">{a.name}</span>
      ),
    },
    { key: "type", header: "Type", render: (a) => titleCase(a.type) },
    {
      key: "category",
      header: "Category",
      render: (a) => (a.category ? titleCase(a.category) : "—"),
    },
    {
      key: "parent",
      header: "Parent",
      render: (a) =>
        a.parent_id ? (accountsById.get(a.parent_id)?.name ?? "—") : "—",
    },
    {
      key: "status",
      header: "Status",
      render: (a) => (
        <Badge tone={a.is_active ? "emerald" : "slate"}>
          {a.is_active ? "Active" : "Inactive"}
        </Badge>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            className: "text-right",
            render: (a: ChartOfAccount) => (
              <div className="flex justify-end gap-3">
                <button
                  onClick={() => setEditing(a)}
                  className="text-xs font-medium text-brand-600 hover:text-brand-700"
                >
                  Edit
                </button>
                <button
                  onClick={() => toggleActive(a)}
                  className="text-xs font-medium text-slate-500 hover:text-slate-700"
                >
                  {a.is_active ? "Deactivate" : "Activate"}
                </button>
              </div>
            ),
          } satisfies Column<ChartOfAccount>,
        ]
      : []),
  ];

  return (
    <Card>
      <div className="flex items-center justify-end border-b border-slate-100 p-4">
        {canManage && (
          <Button size="sm" onClick={() => setEditing("new")}>
            <Plus className="h-4 w-4" /> Add account
          </Button>
        )}
      </div>
      <DataTable
        columns={columns}
        rows={accounts.data}
        rowKey={(a) => a.id}
        loading={accounts.loading}
        error={accounts.error}
        emptyTitle="No accounts yet"
      />
      {editing && (
        <EntityFormModal
          key={editing === "new" ? "new" : editing.id}
          open
          onClose={() => setEditing(null)}
          title={editing === "new" ? "Add account" : `Edit ${editing.name}`}
          description={
            editing !== "new"
              ? "Code and type can't be changed once postings reference this account — deactivate and create a replacement instead."
              : undefined
          }
          fields={fields}
          initialValues={initialValues(editing)}
          onSubmit={handleSubmit}
        />
      )}
    </Card>
  );
}
