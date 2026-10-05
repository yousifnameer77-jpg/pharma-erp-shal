"use client";

import { useState } from "react";
import { Plus } from "lucide-react";
import { api } from "@/lib/api";
import { useSimpleList } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { PageHeader } from "@/components/ui/page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
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
import type { Branch, Warehouse } from "@/lib/types";

const TYPE_LABELS: Record<string, string> = {
  main: "رئيسي (Main)",
  sub: "فرعي (Sub)",
  quarantine: "حجر وتوالف (Quarantine)",
  returns: "مرتجعات (Returns)",
};

function WarehousesContent() {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.branchesManage);
  const toast = useToast();

  const [editing, setEditing] = useState<Warehouse | "new" | null>(null);
  const branches = useSimpleList<Branch>("/v1/branches");
  const warehouses = useSimpleList<Warehouse>("/v1/warehouses");

  const fields: FormFieldSchema[] = [
    {
      name: "branch_id",
      label: "الفرع / المكتب",
      type: "select",
      required: true,
      options: branches.data.map((b) => ({ value: b.id, label: b.name })),
    },
    { name: "name", label: "اسم المستودع / المخزن", required: true },
    { name: "code", label: "رمز المخزن", required: true },
    {
      name: "type",
      label: "نوع المخزن",
      type: "select",
      required: true,
      options: Object.entries(TYPE_LABELS).map(([value, label]) => ({
        value,
        label,
      })),
    },
    { name: "location", label: "الموقع الجغرافي / العنوان", type: "textarea", colSpan: 2 },
    { name: "is_active", label: "نشط ومتاح للعمل", type: "checkbox" },
  ];

  function initialValues(w: Warehouse | "new"): Record<string, FormValue> {
    if (w === "new")
      return {
        type: "main",
        is_active: true,
        branch_id: user?.branch?.id ?? "",
      };
    return {
      branch_id: w.branch_id,
      name: w.name,
      code: w.code,
      type: w.type,
      location: w.location ?? "",
      is_active: w.is_active,
    };
  }

  async function handleSubmit(values: Record<string, FormValue>) {
    if (editing === "new") {
      await api.post("/v1/warehouses", values);
      toast.success("Warehouse created.");
    } else if (editing) {
      await api.put(`/v1/warehouses/${editing.id}`, values);
      toast.success("Warehouse updated.");
    }
    warehouses.refetch();
  }

  async function toggleActive(w: Warehouse) {
    await api.put(`/v1/warehouses/${w.id}`, { is_active: !w.is_active });
    toast.success(
      w.is_active ? "تم تعطيل المخزن." : "تم تفعيل المخزن بنجاح.",
    );
    warehouses.refetch();
  }

  const columns: Column<Warehouse>[] = [
    {
      key: "name",
      header: "المستودع / المخزن",
      render: (w) => (
        <span className="font-semibold text-slate-900">{w.name}</span>
      ),
    },
    { key: "code", header: "الرمز", render: (w) => <span className="font-mono text-xs">{w.code}</span> },
    { key: "branch", header: "الفرع / المكتب", render: (w) => w.branch?.name ?? "—" },
    {
      key: "type",
      header: "نوع المخزن",
      render: (w) => TYPE_LABELS[w.type] ?? w.type,
    },
    { key: "location", header: "الموقع الجغرافي", render: (w) => w.location ?? "—" },
    {
      key: "status",
      header: "الحالة",
      render: (w) => (
        <Badge tone={w.is_active ? "emerald" : "slate"}>
          {w.is_active ? "نشط" : "معطل"}
        </Badge>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            className: "text-left",
            render: (w: Warehouse) => (
              <div className="flex justify-end gap-3">
                <button
                  onClick={() => setEditing(w)}
                  className="text-xs font-semibold text-emerald-700 hover:text-emerald-800"
                >
                  تعديل
                </button>
                <button
                  onClick={() => toggleActive(w)}
                  className="text-xs font-medium text-slate-500 hover:text-slate-700"
                >
                  {w.is_active ? "تعطيل" : "تفعيل"}
                </button>
              </div>
            ),
          } satisfies Column<Warehouse>,
        ]
      : []),
  ];

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="المخازن والمستودعات"
        description="إدارة مستودعات الفروع والمخازن الرئيسية وحركات المناقلة والتوزيع."
        action={
          canManage && (
            <Button size="sm" onClick={() => setEditing("new")} className="bg-emerald-700 hover:bg-emerald-800">
              <Plus className="h-4 w-4" /> إضافة مخزن جديد
            </Button>
          )
        }
      />
      <Card>
        <DataTable
          columns={columns}
          rows={warehouses.data}
          rowKey={(w) => w.id}
          loading={warehouses.loading}
          error={warehouses.error}
          emptyTitle="لا توجد مخازن مضافة بعد"
        />
      </Card>
      {editing && (
        <EntityFormModal
          key={editing === "new" ? "new" : editing.id}
          open
          onClose={() => setEditing(null)}
          title={editing === "new" ? "إضافة مخزن جديد" : `تعديل بيانات ${editing.name}`}
          submitLabel={editing === "new" ? "إضافة المخزن" : "حفظ التعديلات"}
          fields={fields}
          initialValues={initialValues(editing)}
          onSubmit={handleSubmit}
        />
      )}
    </div>
  );
}

export default function WarehousesPage() {
  return (
    <PermissionGate permission={PERMISSIONS.branchesManage}>
      <WarehousesContent />
    </PermissionGate>
  );
}
