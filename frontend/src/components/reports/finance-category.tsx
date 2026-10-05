"use client";

import { useState } from "react";
import { Tabs } from "@/components/ui/tabs";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { CustomerDebtReport } from "@/components/reports/finance/customer-debt-report";
import { SupplierDebtReport } from "@/components/reports/finance/supplier-debt-report";
import { ProfitLossReport } from "@/components/reports/finance/profit-loss-report";

const TABS = [
  { key: "customer-debt", label: "ديون العملاء (الذمم المدينة)" },
  { key: "supplier-debt", label: "ديون الموردين (الذمم الدائنة)" },
  { key: "profit-loss", label: "الأرباح والخسائر الشاملة" },
];

export function FinanceReportsCategory({ companyId }: { companyId: string }) {
  const [active, setActive] = useState("customer-debt");

  return (
    <PermissionGate permission={PERMISSIONS.financeJournalView}>
      <div className="flex flex-col gap-4">
        <Tabs tabs={TABS} active={active} onChange={setActive} />
        {active === "customer-debt" && (
          <CustomerDebtReport companyId={companyId} />
        )}
        {active === "supplier-debt" && (
          <SupplierDebtReport companyId={companyId} />
        )}
        {active === "profit-loss" && <ProfitLossReport companyId={companyId} />}
      </div>
    </PermissionGate>
  );
}
