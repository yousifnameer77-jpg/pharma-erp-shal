"use client";

import { useMemo, useState } from "react";
import { Plus } from "lucide-react";
import { api, ApiError } from "@/lib/api";
import { useApiResource, useSimpleList } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { titleCase } from "@/lib/format";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Button } from "@/components/ui/button";
import { Input, Textarea, Checkbox } from "@/components/ui/field";
import { Badge } from "@/components/ui/badge";
import { Modal } from "@/components/ui/modal";
import { Drawer, DetailSection } from "@/components/ui/drawer";
import { useToast } from "@/components/ui/toast";
import type { Permission, Role } from "@/lib/types";

export function RolesTab() {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.rolesManage);
  const toast = useToast();

  const roles = useSimpleList<Role>("/v1/roles");
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<Role | null>(null);
  const [selected, setSelected] = useState<Role | null>(null);
  const [busy, setBusy] = useState(false);

  async function remove(role: Role) {
    if (
      !confirm(
        `هل أنت متأكد من حذف دور "${role.name}"؟ سيفقد الموظفون المرتبطون به كافة الصلاحيات الممنوحة فوراً.`,
      )
    )
      return;
    setBusy(true);
    try {
      await api.del(`/v1/roles/${role.id}`);
      toast.success("تم حذف الدور بنجاح.");
      roles.refetch();
      setSelected(null);
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "تعذر حذف هذا الدور.",
      );
    } finally {
      setBusy(false);
    }
  }

  const columns: Column<Role>[] = [
    {
      key: "name",
      header: "اسم الدور الوظيفي",
      render: (r) => (
        <span className="font-medium text-slate-900">{r.name}</span>
      ),
    },
    {
      key: "description",
      header: "الوصف والمسؤولية",
      render: (r) => r.description ?? "—",
    },
    {
      key: "permissions",
      header: "عدد الصلاحيات",
      render: (r) => `${r.permissions?.length ?? 0} صلاحية`,
      className: "text-right",
    },
    {
      key: "type",
      header: "نوع الدور",
      render: (r) => (
        <Badge tone={r.is_system_role ? "violet" : "slate"}>
          {r.is_system_role ? "نظام أساسي" : "مخصص"}
        </Badge>
      ),
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            className: "text-left",
            render: (r: Role) =>
              r.is_system_role ? (
                <span className="text-xs text-slate-400">دور محمي</span>
              ) : (
                <div className="flex justify-end gap-3">
                  <button
                    onClick={() => setEditing(r)}
                    className="text-xs font-medium text-brand-600 hover:text-brand-700"
                  >
                    تعديل
                  </button>
                  <button
                    onClick={() => remove(r)}
                    disabled={busy}
                    className="text-xs font-medium text-red-500 hover:text-red-600"
                  >
                    حذف
                  </button>
                </div>
              ),
          } satisfies Column<Role>,
        ]
      : []),
  ];

  return (
    <Card>
      <div className="flex items-center justify-end border-b border-slate-100 p-4">
        {canManage && (
          <Button size="sm" onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" /> إضافة دور جديد
          </Button>
        )}
      </div>
      <DataTable
        columns={columns}
        rows={roles.data}
        rowKey={(r) => r.id}
        loading={roles.loading}
        error={roles.error}
        onRowClick={(r) => setSelected(r)}
        emptyTitle="لا توجد أدوار مضافة حالياً"
      />

      {(creating || editing) && (
        <RoleFormModal
          role={editing}
          onClose={() => {
            setCreating(false);
            setEditing(null);
          }}
          onDone={() => {
            setCreating(false);
            setEditing(null);
            roles.refetch();
          }}
        />
      )}

      {selected && (
        <Drawer
          open
          onClose={() => setSelected(null)}
          title={selected.name}
          subtitle={selected.description ?? undefined}
        >
          <DetailSection title="مصفوفة الصلاحيات المعتمدة لهذا الدور">
            {Object.entries(groupByModule(selected.permissions ?? [])).map(
              ([module, perms]) => (
                <div key={module} className="py-2">
                  <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                    {titleCase(module)}
                  </p>
                  <div className="flex flex-wrap gap-1.5">
                    {perms.map((p) => (
                      <Badge key={p.id} tone="blue">
                        {p.code}
                      </Badge>
                    ))}
                  </div>
                </div>
              ),
            )}
            {(selected.permissions ?? []).length === 0 && (
              <p className="text-xs text-slate-400">لا توجد صلاحيات معتمدة لهذا الدور بعد.</p>
            )}
          </DetailSection>
        </Drawer>
      )}
    </Card>
  );
}

function groupByModule(
  permissions: Permission[],
): Record<string, Permission[]> {
  return permissions.reduce<Record<string, Permission[]>>((acc, p) => {
    (acc[p.module] ??= []).push(p);
    return acc;
  }, {});
}

function RoleFormModal({
  role,
  onClose,
  onDone,
}: {
  role: Role | null;
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const [name, setName] = useState(role?.name ?? "");
  const [description, setDescription] = useState(role?.description ?? "");
  const [selectedIds, setSelectedIds] = useState<Set<string>>(
    new Set((role?.permissions ?? []).map((p) => p.id)),
  );
  const [busy, setBusy] = useState(false);

  const catalogue =
    useApiResource<Record<string, Permission[]>>("/v1/permissions");
  const modules = useMemo(
    () => Object.entries(catalogue.data ?? {}),
    [catalogue.data],
  );

  function toggle(id: string) {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function toggleModule(perms: Permission[], enable: boolean) {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      for (const p of perms) {
        if (enable) next.add(p.id);
        else next.delete(p.id);
      }
      return next;
    });
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      const payload = {
        name,
        description: description || undefined,
        permission_ids: Array.from(selectedIds),
      };
      if (role) {
        await api.put(`/v1/roles/${role.id}`, payload);
        toast.success("تم تحديث الدور بنجاح.");
      } else {
        await api.post("/v1/roles", payload);
        toast.success("تم إنشاء الدور بنجاح.");
      }
      onDone();
    } catch (err) {
      toast.error(
        err instanceof ApiError ? err.summary : "تعذر حفظ بيانات هذا الدور.",
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={role ? `تعديل الدور: ${role.name}` : "إضافة دور وظيفي جديد"}
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} type="button">
            إلغاء
          </Button>
          <Button onClick={submit} loading={busy}>
            {role ? "حفظ التعديلات" : "إنشاء الدور"}
          </Button>
        </>
      }
    >
      <form onSubmit={submit} className="grid grid-cols-2 gap-4">
        <Input
          label="اسم الدور الوظيفي"
          required
          value={name}
          onChange={(e) => setName(e.target.value)}
        />
        <Textarea
          label="الوصف ومجال المسؤولية"
          value={description}
          onChange={(e) => setDescription(e.target.value)}
        />

        <div className="col-span-2 max-h-80 space-y-4 overflow-y-auto rounded-lg border border-slate-200 p-4">
          {catalogue.loading && (
            <p className="text-xs text-slate-400">جاري تحميل شجرة الصلاحيات المتاحة...</p>
          )}
          {modules.map(([module, perms]) => {
            const allSelected = perms.every((p) => selectedIds.has(p.id));
            return (
              <div key={module}>
                <div className="mb-1.5 flex items-center justify-between">
                  <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {titleCase(module)}
                  </p>
                  <button
                    type="button"
                    onClick={() => toggleModule(perms, !allSelected)}
                    className="text-xs font-medium text-brand-600 hover:text-brand-700"
                  >
                    {allSelected ? "إلغاء تحديد الكل" : "تحديد الكل"}
                  </button>
                </div>
                <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                  {perms.map((p) => (
                    <Checkbox
                      key={p.id}
                      label={p.description || p.code}
                      checked={selectedIds.has(p.id)}
                      onChange={() => toggle(p.id)}
                    />
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      </form>
    </Modal>
  );
}
