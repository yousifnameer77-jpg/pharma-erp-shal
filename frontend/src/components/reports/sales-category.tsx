"use client";

import { useState } from "react";
import { Tabs } from "@/components/ui/tabs";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { DailySalesReport } from "@/components/reports/sales/daily-sales-report";
import { MonthlySalesReport } from "@/components/reports/sales/monthly-sales-report";
import { SalesByBranchReport } from "@/components/reports/sales/sales-by-branch-report";

const TABS = [
  { key: "daily", label: "المبيعات اليومية" },
  { key: "monthly", label: "المبيعات الشهرية" },
  { key: "by-branch", label: "المبيعات حسب الفروع والمكاتب" },
];

export function SalesReportsCategory({ companyId }: { companyId: string }) {
  const [active, setActive] = useState("daily");

  return (
    <PermissionGate permission={PERMISSIONS.salesView}>
      <div className="flex flex-col gap-4">
        <Tabs tabs={TABS} active={active} onChange={setActive} />
        {active === "daily" && <DailySalesReport companyId={companyId} />}
        {active === "monthly" && <MonthlySalesReport companyId={companyId} />}
        {active === "by-branch" && (
          <SalesByBranchReport companyId={companyId} />
        )}
      </div>
    </PermissionGate>
  );
}
