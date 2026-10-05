"use client";

import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Tabs } from "@/components/ui/tabs";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { useAuth } from "@/lib/auth-context";
import { PurchaseRequestsTab } from "@/components/purchases/requests-tab";
import { PurchaseOrdersTab } from "@/components/purchases/orders-tab";
import { GoodsReceiptsTab } from "@/components/purchases/receipts-tab";
import { PurchaseInvoicesTab } from "@/components/purchases/invoices-tab";
import { SupplierPaymentsTab } from "@/components/purchases/payments-tab";
import { ReorderTab } from "@/components/purchases/reorder-tab";

const TABS = [
  { key: "reorder", label: "⚡ إعادة الطلب التلقائي الذكي" },
  { key: "requests", label: "طلبات الشراء" },
  { key: "orders", label: "أوامر الشراء الرسمية" },
  { key: "receipts", label: "سندات استلام البضاعة (الوارد)" },
  { key: "invoices", label: "فواتير الموردين" },
  { key: "payments", label: "سندات صرف المدفوعات" },
];

function PurchasesContent({ companyId }: { companyId: string }) {
  const [active, setActive] = useState("reorder");

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="إدارة المشتريات والتوريد"
        description="دورة المشتريات الكاملة: طلب شراء ← أمر شراء معتمد ← استلام مخزني ← فاتورة مورد ← سداد دفعات وإقفال الحساب."
      />
      <Tabs tabs={TABS} active={active} onChange={setActive} />
      {active === "reorder" && <ReorderTab companyId={companyId} />}
      {active === "requests" && <PurchaseRequestsTab companyId={companyId} />}
      {active === "orders" && <PurchaseOrdersTab companyId={companyId} />}
      {active === "receipts" && <GoodsReceiptsTab companyId={companyId} />}
      {active === "invoices" && <PurchaseInvoicesTab companyId={companyId} />}
      {active === "payments" && <SupplierPaymentsTab companyId={companyId} />}
    </div>
  );
}

export default function PurchasesPage() {
  const { user } = useAuth();
  const companyId =
    user?.company_id ??
    (user as any)?.branch?.company_id ??
    "a2d453ef-3f76-4cc8-8993-4180db747d08";

  return (
    <PermissionGate permission={PERMISSIONS.purchasingView}>
      {companyId ? (
        <PurchasesContent companyId={companyId} />
      ) : null}
    </PermissionGate>
  );
}
