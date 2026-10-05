"use client";

import { useState } from "react";
import { Plus, Wallet } from "lucide-react";
import { api } from "@/lib/api";
import { usePaginatedResource, useApiResource } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import { PageHeader } from "@/components/ui/page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Card } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Pagination } from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/field";
import { Badge } from "@/components/ui/badge";
import { BalanceModal } from "@/components/ui/balance-modal";
import {
  EntityFormModal,
  type FormFieldSchema,
  type FormValue,
} from "@/components/forms/entity-form-modal";
import { useToast } from "@/components/ui/toast";
import type { Supplier, SupplierBalance } from "@/lib/types";

function SuppliersContent({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.productsManage);
  const toast = useToast();

  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState<Supplier | "new" | null>(null);
  const [balanceFor, setBalanceFor] = useState<Supplier | null>(null);

  const list = usePaginatedResource<Supplier>("/v1/suppliers", {
    company_id: companyId,
    search: search || undefined,
  });
  const balance = useApiResource<SupplierBalance>(
    balanceFor ? `/v1/suppliers/${balanceFor.id}/balance` : null,
  );

  const fields: FormFieldSchema[] = [
    { name: "name", label: "اسم المورد / الشركة المصنعة", required: true, colSpan: 2 },
    { name: "contact_person", label: "الشخص المسؤول / المندوب" },
    { name: "phone", label: "رقم الهاتف" },
    { name: "email", label: "البريد الإلكتروني", type: "email" },
    { name: "tax_number", label: "رقم الإجازة / السجل التجاري" },
    { name: "address", label: "العنوان ومقر الشركة", type: "textarea", colSpan: 2 },
    { name: "payment_terms", label: "شروط السداد والدفع", placeholder: "آجل 45 يوماً / نقد" },
    { name: "is_active", label: "حساب نشط", type: "checkbox" },
  ];

  function initialValues(s: Supplier | "new"): Record<string, FormValue> {
    if (s === "new") return { is_active: true };
    return {
      name: s.name,
      contact_person: s.contact_person ?? "",
      phone: s.phone ?? "",
      email: s.email ?? "",
      tax_number: s.tax_number ?? "",
      address: s.address ?? "",
      payment_terms: s.payment_terms ?? "",
      is_active: s.is_active,
    };
  }

  async function handleSubmit(values: Record<string, FormValue>) {
    if (editing === "new") {
      await api.post("/v1/suppliers", { ...values, company_id: companyId });
      toast.success("تمت إضافة المورد بنجاح.");
    } else if (editing) {
      await api.put(`/v1/suppliers/${editing.id}`, values);
      toast.success("تم تحديث بيانات المورد بنجاح.");
    }
    list.refetch();
  }

  async function toggleActive(s: Supplier) {
    await api.put(`/v1/suppliers/${s.id}`, { is_active: !s.is_active });
    toast.success(
      s.is_active ? "تم تعطيل حساب المورد." : "تم تنشيط حساب المورد.",
    );
    list.refetch();
  }

  const columns: Column<Supplier>[] = [
    {
      key: "name",
      header: "اسم المورد / الشركة",
      render: (s) => (
        <span className="font-medium text-slate-900">{s.name}</span>
      ),
    },
    {
      key: "contact",
      header: "المسؤول / المندوب",
      render: (s) => s.contact_person ?? "—",
    },
    { key: "phone", header: "الهاتف", render: (s) => s.phone ?? "—" },
    { key: "terms", header: "شروط السداد", render: (s) => s.payment_terms ?? "—" },
    {
      key: "status",
      header: "الحالة",
      render: (s) => (
        <Badge tone={s.is_active ? "emerald" : "slate"}>
          {s.is_active ? "نشط" : "معطل"}
        </Badge>
      ),
    },
    {
      key: "actions",
      header: "",
      className: "text-left",
      render: (s) => (
        <div className="flex justify-end gap-3">
          <button
            onClick={() => setBalanceFor(s)}
            className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-700"
          >
            <Wallet className="h-3.5 w-3.5" /> كشف الحساب
          </button>
          {canManage && (
            <>
              <button
                onClick={() => setEditing(s)}
                className="text-xs font-medium text-brand-600 hover:text-brand-700"
              >
                تعديل
              </button>
              <button
                onClick={() => toggleActive(s)}
                className="text-xs font-medium text-slate-500 hover:text-slate-700"
              >
                {s.is_active ? "تعطيل" : "تفعيل"}
              </button>
            </>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="الموردون والشركات المصنعة"
        description="سجل الشركات الموردة للأدوية واللقاحات — متابعة الذمم الدائنة، فواتير الشراء، والدفعات المالية."
        action={
          canManage && (
            <Button size="sm" onClick={() => setEditing("new")}>
              <Plus className="h-4 w-4" /> إضافة مورد جديد
            </Button>
          )
        }
      />
      <Card>
        <div className="border-b border-slate-100 p-4">
          <Input
            placeholder="بحث باسم المورد، المسؤول، أو الهاتف..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full sm:w-72"
          />
        </div>
        <DataTable
          columns={columns}
          rows={list.data}
          rowKey={(s) => s.id}
          loading={list.loading}
          error={list.error}
          emptyTitle="لا يوجد موردون مسجلون حالياً"
        />
        <Pagination
          meta={list.meta}
          page={list.page}
          onPageChange={list.setPage}
        />
      </Card>

      {editing && (
        <EntityFormModal
          key={editing === "new" ? "new" : editing.id}
          open
          onClose={() => setEditing(null)}
          title={editing === "new" ? "إضافة مورد جديد" : `تعديل بيانات ${editing.name}`}
          submitLabel="حفظ البيانات"
          fields={fields}
          initialValues={initialValues(editing)}
          onSubmit={handleSubmit}
          size="lg"
        />
      )}

      {balanceFor && (
        <BalanceModal
          open
          onClose={() => setBalanceFor(null)}
          title={`كشف حساب المورد: ${balanceFor.name}`}
          loading={balance.loading}
          rows={[
            {
              label: "إجمالي فواتير الشراء",
              value: balance.data?.total_invoiced ?? 0,
            },
            { label: "إجمالي المدفوعات المسددة للمورد", value: balance.data?.total_paid ?? 0 },
          ]}
          balance={balance.data?.balance ?? 0}
        />
      )}
    </div>
  );
}

export default function SuppliersPage() {
  const { user } = useAuth();
  const companyId =
    user?.company_id ??
    (user as any)?.branch?.company_id ??
    "a2d453ef-3f76-4cc8-8993-4180db747d08";

  return (
    <PermissionGate permission={PERMISSIONS.productsView}>
      {companyId ? (
        <SuppliersContent companyId={companyId} />
      ) : null}
    </PermissionGate>
  );
}
