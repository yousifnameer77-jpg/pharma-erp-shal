"use client";

import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Tabs } from "@/components/ui/tabs";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { useAuth } from "@/lib/auth-context";
import { SalesInvoicesTab } from "@/components/sales/invoices-tab";
import { SalesReturnsTab } from "@/components/sales/returns-tab";
import { CustomerPaymentsTab } from "@/components/sales/payments-tab";

const TABS = [
  { key: "invoices", label: "فواتير المبيعات" },
  { key: "returns", label: "مرتجعات المبيعات" },
  { key: "payments", label: "سندات القبض والدفعات" },
];

function SalesContent({ companyId }: { companyId: string }) {
  const [active, setActive] = useState("invoices");

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="المبيعات وفواتير العملاء"
        description="إصدار فواتير بيع مع صرف تلقائي بنظام FEFO (الأقرب انتهاءً أولاً)، إدارة المرتجعات، وقبض دفعات وأرصدة العملاء."
      />
      <Tabs tabs={TABS} active={active} onChange={setActive} />
      {active === "invoices" && <SalesInvoicesTab companyId={companyId} />}
      {active === "returns" && <SalesReturnsTab companyId={companyId} />}
      {active === "payments" && <CustomerPaymentsTab companyId={companyId} />}
    </div>
  );
}

export default function SalesPage() {
  const { user } = useAuth();
  const companyId =
    user?.company_id ??
    (user as any)?.branch?.company_id ??
    "a2d453ef-3f76-4cc8-8993-4180db747d08";

  return (
    <PermissionGate permission={PERMISSIONS.salesView}>
      {companyId ? <SalesContent companyId={companyId} /> : null}
    </PermissionGate>
  );
}
