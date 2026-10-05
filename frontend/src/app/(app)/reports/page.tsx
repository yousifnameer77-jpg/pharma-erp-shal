"use client";

import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Tabs } from "@/components/ui/tabs";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { useAuth } from "@/lib/auth-context";
import { InventoryReportsCategory } from "@/components/reports/inventory-category";
import { SalesReportsCategory } from "@/components/reports/sales-category";
import { FinanceReportsCategory } from "@/components/reports/finance-category";

const TABS = [
  { key: "inventory", label: "تقارير المخزون والأدوية" },
  { key: "sales", label: "تقارير المبيعات والعملاء" },
  { key: "finance", label: "التقارير المالية والذمم" },
];

function ReportsContent({ companyId }: { companyId: string }) {
  const [active, setActive] = useState("inventory");

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="التقارير والتحليلات الشاملة"
        description="تقارير حركة المخزون، المبيعات، الأرباح، والذمم المدينة والدائنة — مع إمكانية التصدير إلى Excel وPDF والطباعة المباشرة."
      />
      <Tabs tabs={TABS} active={active} onChange={setActive} />
      {active === "inventory" && (
        <InventoryReportsCategory companyId={companyId} />
      )}
      {active === "sales" && <SalesReportsCategory companyId={companyId} />}
      {active === "finance" && <FinanceReportsCategory companyId={companyId} />}
    </div>
  );
}

export default function ReportsPage() {
  const { user } = useAuth();
  const companyId =
    user?.company_id ??
    (user as any)?.branch?.company_id ??
    "a2d453ef-3f76-4cc8-8993-4180db747d08";

  return (
    <PermissionGate permission={PERMISSIONS.reportsView}>
      {companyId ? <ReportsContent companyId={companyId} /> : null}
    </PermissionGate>
  );
}
