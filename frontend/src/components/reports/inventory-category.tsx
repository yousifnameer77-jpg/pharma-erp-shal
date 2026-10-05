"use client";

import { useState } from "react";
import { Tabs } from "@/components/ui/tabs";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { StockBalanceReport } from "@/components/reports/inventory/stock-balance-report";
import { ExpiringProductsReport } from "@/components/reports/inventory/expiring-products-report";
import { ExpiredProductsReport } from "@/components/reports/inventory/expired-products-report";
import { BatchReport } from "@/components/reports/inventory/batch-report";
import { ExpiryRiskReport } from "@/components/reports/inventory/expiry-risk-report";

const TABS = [
  { key: "expiry-risk", label: "🚨 رقابة الصلاحيات والحجر (Expiry Risk)" },
  { key: "stock-balance", label: "أرصدة المخزون وحركة المواد" },
  { key: "expiring", label: "الأدوية القريبة من الانتهاء" },
  { key: "expired", label: "الأدوية منتهية الصلاحية (المحجوزة)" },
  { key: "batches", label: "تقرير التشغيلات والوجبات (Batches)" },
];

export function InventoryReportsCategory({ companyId }: { companyId: string }) {
  const [active, setActive] = useState("expiry-risk");

  return (
    <PermissionGate permission={PERMISSIONS.productsView}>
      <div className="flex flex-col gap-4">
        <Tabs tabs={TABS} active={active} onChange={setActive} />
        {active === "expiry-risk" && <ExpiryRiskReport />}
        {active === "stock-balance" && <StockBalanceReport />}
        {active === "expiring" && <ExpiringProductsReport />}
        {active === "expired" && <ExpiredProductsReport />}
        {active === "batches" && <BatchReport companyId={companyId} />}
      </div>
    </PermissionGate>
  );
}
