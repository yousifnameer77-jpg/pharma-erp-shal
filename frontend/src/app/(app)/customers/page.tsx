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
import type { Customer, CustomerBalance } from "@/lib/types";

function CustomersContent({ companyId }: { companyId: string }) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const canManage = isSuperAdmin || hasPermission(PERMISSIONS.salesManage);
  const toast = useToast();

  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState<Customer | "new" | null>(null);
  const [balanceFor, setBalanceFor] = useState<Customer | null>(null);

  const list = usePaginatedResource<Customer>("/v1/customers", {
    company_id: companyId,
    search: search || undefined,
  });
  const balance = useApiResource<CustomerBalance>(
    balanceFor ? `/v1/customers/${balanceFor.id}/balance` : null,
  );

  const fields: FormFieldSchema[] = [
    { name: "name", label: "اسم العميل / المنشأة / المزرعة", required: true, colSpan: 2 },
    { name: "contact_person", label: "الشخص المسؤول / الطبيب" },
    { name: "phone", label: "رقم الهاتف" },
    { name: "email", label: "البريد الإلكتروني", type: "email" },
    { name: "tax_number", label: "رقم الإجازة / الهوية الضريبية" },
    { name: "address", label: "العنوان التفصيلي", type: "textarea", colSpan: 2 },
    {
      name: "credit_limit",
      label: "سقف الائتمان (د.ع)",
      type: "number",
      step: "0.001",
      min: 0,
    },
    { name: "payment_terms", label: "شروط الدفع", placeholder: "آجل 30 يوماً / نقد" },
    { name: "is_active", label: "حساب نشط", type: "checkbox" },
  ];

  function initialValues(c: Customer | "new"): Record<string, FormValue> {
    if (c === "new") return { is_active: true };
    return {
      name: c.name,
      contact_person: c.contact_person ?? "",
      phone: c.phone ?? "",
      email: c.email ?? "",
      tax_number: c.tax_number ?? "",
      address: c.address ?? "",
      credit_limit: c.credit_limit ?? "",
      payment_terms: c.payment_terms ?? "",
      is_active: c.is_active,
    };
  }

  async function handleSubmit(values: Record<string, FormValue>) {
    if (editing === "new") {
      await api.post("/v1/customers", { ...values, company_id: companyId });
      toast.success("تم إنشاء حساب العميل بنجاح.");
    } else if (editing) {
      await api.put(`/v1/customers/${editing.id}`, values);
      toast.success("تم تحديث بيانات العميل بنجاح.");
    }
    list.refetch();
  }

  async function toggleActive(c: Customer) {
    await api.put(`/v1/customers/${c.id}`, { is_active: !c.is_active });
    toast.success(
      c.is_active ? "تم تعطيل حساب العميل." : "تم تنشيط حساب العميل.",
    );
    list.refetch();
  }

  const columns: Column<Customer>[] = [
    {
      key: "name",
      header: "اسم العميل / الجهة",
      render: (c) => (
        <span className="font-medium text-slate-900">{c.name}</span>
      ),
    },
    {
      key: "contact",
      header: "المسؤول / الطبيب",
      render: (c) => c.contact_person ?? "—",
    },
    { key: "phone", header: "الهاتف", render: (c) => c.phone ?? "—" },
    { key: "terms", header: "شروط السداد", render: (c) => c.payment_terms ?? "—" },
    {
      key: "status",
      header: "الحالة",
      render: (c) => (
        <Badge tone={c.is_active ? "emerald" : "slate"}>
          {c.is_active ? "نشط" : "معطل"}
        </Badge>
      ),
    },
    {
      key: "actions",
      header: "",
      className: "text-left",
      render: (c) => (
        <div className="flex justify-end gap-3">
          <button
            onClick={() => setBalanceFor(c)}
            className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-700"
          >
            <Wallet className="h-3.5 w-3.5" /> كشف الحساب
          </button>
          {canManage && (
            <>
              <button
                onClick={() => setEditing(c)}
                className="text-xs font-medium text-brand-600 hover:text-brand-700"
              >
                تعديل
              </button>
              <button
                onClick={() => toggleActive(c)}
                className="text-xs font-medium text-slate-500 hover:text-slate-700"
              >
                {c.is_active ? "تعطيل" : "تفعيل"}
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
        title="العملاء والجهات البيطرية"
        description="إدارة حسابات العيادات، المزارع، والوكلاء — كشف أرصدة فوري، شروط الدفع، وسقف الائتمان."
        action={
          canManage && (
            <Button size="sm" onClick={() => setEditing("new")}>
              <Plus className="h-4 w-4" /> إضافة عميل جديد
            </Button>
          )
        }
      />
      <Card>
        <div className="border-b border-slate-100 p-4">
          <Input
            placeholder="بحث بالاسم، المسؤول، أو رقم الهاتف..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full sm:w-72"
          />
        </div>
        <DataTable
          columns={columns}
          rows={list.data}
          rowKey={(c) => c.id}
          loading={list.loading}
          error={list.error}
          emptyTitle="لا يوجد عملاء مسجلون حالياً"
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
          title={editing === "new" ? "إضافة عميل جديد" : `تعديل بيانات ${editing.name}`}
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
          title={`كشف حساب: ${balanceFor.name}`}
          loading={balance.loading}
          rows={[
            {
              label: "إجمالي الفواتير الصادرة",
              value: balance.data?.total_invoiced ?? 0,
            },
            {
              label: "إجمالي المردودات الدائنة",
              value: balance.data?.total_returned ?? 0,
            },
            { label: "إجمالي المقبوضات المسددة", value: balance.data?.total_paid ?? 0 },
          ]}
          balance={balance.data?.balance ?? 0}
        />
      )}
    </div>
  );
}

export default function CustomersPage() {
  const { user } = useAuth();
  const companyId =
    user?.company_id ??
    (user as any)?.branch?.company_id ??
    "a2d453ef-3f76-4cc8-8993-4180db747d08";

  return (
    <PermissionGate permission={PERMISSIONS.salesView}>
      {companyId ? (
        <CustomersContent companyId={companyId} />
      ) : null}
    </PermissionGate>
  );
}
