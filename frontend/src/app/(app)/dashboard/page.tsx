"use client";

import Link from "next/link";
import {
  Package,
  AlertTriangle,
  Users,
  Truck,
  Landmark,
  TrendingUp,
  TrendingDown,
  Wallet,
} from "lucide-react";
import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";
import {
  useApiResource,
  usePaginatedResource,
  useSimpleList,
} from "@/lib/hooks";
import { formatDate, formatMoney, todayIso } from "@/lib/format";
import { PageHeader } from "@/components/ui/page-header";
import { ExecutiveAnalytics } from "@/components/dashboard/executive-analytics";
import { StatCard } from "@/components/ui/stat-card";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { DataTable } from "@/components/ui/table";
import { StatusBadge } from "@/components/ui/badge";
import type {
  Product,
  ProfitAndLoss,
  PurchaseOrder,
  ReceivablesAging,
  PayablesAging,
  SalesInvoice,
} from "@/lib/types";

function firstOfMonthIso(): string {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
}

function CatalogStats() {
  const products = usePaginatedResource<Product>("/v1/products", {
    per_page: 1,
  });
  const lowStock = useSimpleList<Product>("/v1/products/low-stock");

  return (
    <>
      <StatCard
        label="إجمالي أصناف الأدوية"
        value={products.loading ? "…" : (products.meta?.total ?? 0)}
        icon={Package}
      />
      <StatCard
        label="تنبيهات نقص المخزون"
        value={lowStock.loading ? "…" : lowStock.data.length}
        icon={AlertTriangle}
        tone={lowStock.data.length > 0 ? "amber" : "slate"}
        hint={lowStock.data.length > 0 ? "يتطلب إعادة الطلب" : "المخزون كافي"}
      />
    </>
  );
}

function PartyStats({ companyId }: { companyId: string }) {
  const customers = usePaginatedResource<unknown>("/v1/customers", {
    company_id: companyId,
    per_page: 1,
  });
  const suppliers = usePaginatedResource<unknown>("/v1/suppliers", {
    company_id: companyId,
    per_page: 1,
  });

  return (
    <>
      <StatCard
        label="العملاء والجهات"
        value={customers.loading ? "…" : (customers.meta?.total ?? 0)}
        icon={Users}
      />
      <StatCard
        label="الموردون والشركات"
        value={suppliers.loading ? "…" : (suppliers.meta?.total ?? 0)}
        icon={Truck}
      />
    </>
  );
}

function FinanceStats({ companyId }: { companyId: string }) {
  const receivables = useApiResource<ReceivablesAging>(
    "/v1/accounting/receivables",
    { company_id: companyId },
  );
  const payables = useApiResource<PayablesAging>("/v1/accounting/payables", {
    company_id: companyId,
  });
  const pnl = useApiResource<ProfitAndLoss>("/v1/accounting/profit-and-loss", {
    company_id: companyId,
    date_from: firstOfMonthIso(),
    date_to: todayIso(),
  });

  return (
    <>
      <StatCard
        label="الذمم المدينة (ديون العملاء)"
        value={
          receivables.loading
            ? "…"
            : formatMoney(receivables.data?.grand_total ?? 0)
        }
        icon={Wallet}
        tone="blue"
      />
      <StatCard
        label="الذمم الدائنة (مستحقات الموردين)"
        value={
          payables.loading ? "…" : formatMoney(payables.data?.grand_total ?? 0)
        }
        icon={Landmark}
        tone="amber"
      />
      <StatCard
        label="صافي أرباح الشهر الحالي"
        value={pnl.loading ? "…" : formatMoney(pnl.data?.net_profit ?? 0)}
        icon={(pnl.data?.net_profit ?? 0) >= 0 ? TrendingUp : TrendingDown}
        tone={(pnl.data?.net_profit ?? 0) >= 0 ? "emerald" : "red"}
      />
    </>
  );
}

function MonthlyPnlChart({ companyId }: { companyId: string }) {
  const pnl = useApiResource<ProfitAndLoss>("/v1/accounting/profit-and-loss", {
    company_id: companyId,
    date_from: firstOfMonthIso(),
    date_to: todayIso(),
  });

  const chartData = [
    { name: "الإيرادات", value: pnl.data?.total_revenue ?? 0 },
    { name: "المصروفات", value: pnl.data?.total_expense ?? 0 },
    { name: "صافي الأرباح", value: pnl.data?.net_profit ?? 0 },
  ];

  return (
    <Card>
      <CardHeader
        title="مؤشرات الأداء المالي للشهر الحالي"
        description="الإيرادات، المصروفات، وصافي الأرباح التراكمية حتى اليوم"
      />
      <CardBody>
        {pnl.loading ? (
          <div className="h-56 animate-pulse rounded-lg bg-slate-50" />
        ) : (
          <div className="h-56">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={chartData} margin={{ left: -12 }}>
                <CartesianGrid
                  strokeDasharray="3 3"
                  vertical={false}
                  stroke="#e2e8f0"
                />
                <XAxis
                  dataKey="name"
                  tick={{ fontSize: 12, fill: "#64748b" }}
                  axisLine={false}
                  tickLine={false}
                />
                <YAxis
                  tick={{ fontSize: 12, fill: "#64748b" }}
                  axisLine={false}
                  tickLine={false}
                />
                <Tooltip
                  formatter={(v: number) => formatMoney(v)}
                  contentStyle={{
                    borderRadius: 8,
                    borderColor: "#e2e8f0",
                    fontSize: 12,
                  }}
                />
                <Bar dataKey="value" fill="#4a6bf5" radius={[6, 6, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

function RecentSalesCard({ companyId }: { companyId: string }) {
  const invoices = usePaginatedResource<SalesInvoice>("/v1/sales-invoices", {
    company_id: companyId,
    per_page: 5,
  });

  return (
    <Card>
      <CardHeader
        title="أحدث فواتير المبيعات"
        action={
          <Link
            href="/sales"
            className="text-xs font-medium text-brand-600 hover:text-brand-700"
          >
            عرض الكل
          </Link>
        }
      />
      <DataTable
        rows={invoices.data}
        loading={invoices.loading}
        error={invoices.error}
        rowKey={(r) => r.id}
        emptyTitle="لا توجد فواتير مبيعات بعد"
        columns={[
          {
            key: "invoice_number",
            header: "رقم الفاتورة",
            render: (r) => r.invoice_number,
          },
          {
            key: "customer",
            header: "العميل / الجهة",
            render: (r) => r.customer?.name ?? "—",
          },
          {
            key: "date",
            header: "التاريخ",
            render: (r) => formatDate(r.invoice_date),
          },
          {
            key: "total",
            header: "الإجمالي",
            render: (r) => formatMoney(r.total_amount),
            className: "text-start",
          },
          {
            key: "status",
            header: "الحالة",
            render: (r) => <StatusBadge status={r.status} />,
          },
        ]}
      />
    </Card>
  );
}

function RecentPurchasesCard({ companyId }: { companyId: string }) {
  const orders = usePaginatedResource<PurchaseOrder>("/v1/purchase-orders", {
    company_id: companyId,
    per_page: 5,
  });

  return (
    <Card>
      <CardHeader
        title="أحدث أوامر الشراء والتوريد"
        action={
          <Link
            href="/purchases"
            className="text-xs font-medium text-brand-600 hover:text-brand-700"
          >
            عرض الكل
          </Link>
        }
      />
      <DataTable
        rows={orders.data}
        loading={orders.loading}
        error={orders.error}
        rowKey={(r) => r.id}
        emptyTitle="لا توجد أوامر شراء بعد"
        columns={[
          {
            key: "order_number",
            header: "رقم الطلب",
            render: (r) => r.order_number,
          },
          {
            key: "supplier",
            header: "المورد / الشركة",
            render: (r) => r.supplier?.name ?? "—",
          },
          {
            key: "date",
            header: "التاريخ",
            render: (r) => formatDate(r.order_date),
          },
          {
            key: "status",
            header: "الحالة",
            render: (r) => <StatusBadge status={r.status} />,
          },
        ]}
      />
    </Card>
  );
}

export default function DashboardPage() {
  const { user, hasPermission } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const companyId =
    user?.company_id ??
    (user as any)?.branch?.company_id ??
    "a2d453ef-3f76-4cc8-8993-4180db747d08";
  const can = (p: string) => isSuperAdmin || hasPermission(p);

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={`أهلاً بك، ${user?.full_name || "مدير النظام"}`}
        description="إليك ملخص مؤشرات الأعمال، حركة المبيعات والنشاط التشغيلي اليوم."
      />

      <ExecutiveAnalytics />

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        {can(PERMISSIONS.productsView) && <CatalogStats />}
        {companyId && can(PERMISSIONS.salesView) && (
          <PartyStats companyId={companyId} />
        )}
        {companyId && can(PERMISSIONS.financeJournalView) && (
          <FinanceStats companyId={companyId} />
        )}
      </div>

      {companyId && can(PERMISSIONS.financeJournalView) && (
        <MonthlyPnlChart companyId={companyId} />
      )}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {companyId && can(PERMISSIONS.salesView) && (
          <RecentSalesCard companyId={companyId} />
        )}
        {companyId && can(PERMISSIONS.purchasingView) && (
          <RecentPurchasesCard companyId={companyId} />
        )}
      </div>
    </div>
  );
}
