"use client";

import { useState } from "react";
import { AlertTriangle, ShieldAlert, AlertCircle, Clock, ArrowRightLeft, DollarSign } from "lucide-react";
import { StatCard } from "@/components/ui/stat-card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { Spinner } from "@/components/ui/spinner";
import { EmptyState } from "@/components/ui/empty-state";
import { useApiResource } from "@/lib/hooks";
import { api, ApiError } from "@/lib/api";
import { formatMoney, formatNumber } from "@/lib/format";

interface ExpiryItem {
  stock_id: string;
  batch_id: string;
  batch_number: string;
  expiry_date: string;
  days_to_expiry: number;
  risk_level: "expired" | "critical" | "warning" | "notice";
  quantity_on_hand: number;
  reserved_quantity: number;
  unit_cost: number;
  total_risk_value: number;
  warehouse: {
    id: string;
    name: string;
    type: string;
  };
  product: {
    id: string;
    name: string;
    generic_name: string | null;
    code: string;
    barcode: string | null;
  };
  supplier: {
    id: string;
    name: string;
  } | null;
}

interface ExpiryRiskData {
  summary: {
    total_batches_at_risk: number;
    expired_count: number;
    expired_value: number;
    critical_count: number;
    critical_value: number;
    warning_count: number;
    warning_value: number;
    notice_count: number;
    notice_value: number;
    total_financial_risk_value: number;
  };
  items: ExpiryItem[];
}

export function ExpiryRiskReport() {
  const [filterLevel, setFilterLevel] = useState<string>("all");
  const [quarantineTarget, setQuarantineTarget] = useState<ExpiryItem | null>(null);
  const [quarantineQty, setQuarantineQty] = useState<string>("");
  const [quarantineNotes, setQuarantineNotes] = useState<string>("");
  const [submitting, setSubmitting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);

  const { data, loading, error, refetch } = useApiResource<ExpiryRiskData>(
    "/v1/inventory/expiry-risk",
    { risk_level: filterLevel === "all" ? undefined : filterLevel }
  );

  const summary = data?.summary;
  const items = data?.items ?? [];

  const handleOpenQuarantine = (item: ExpiryItem) => {
    setQuarantineTarget(item);
    setQuarantineQty(String(item.quantity_on_hand));
    setQuarantineNotes(`نقل إلى الحجر الصحي بسبب قرب أو انتهاء الصلاحية (${item.days_to_expiry} يوم)`);
    setActionError(null);
    setActionSuccess(null);
  };

  const handleExecuteQuarantine = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!quarantineTarget) return;

    setSubmitting(true);
    setActionError(null);

    try {
      await api.post(`/v1/inventory/batches/${quarantineTarget.batch_id}/quarantine`, {
        from_warehouse_id: quarantineTarget.warehouse.id,
        quantity: parseFloat(quarantineQty),
        notes: quarantineNotes,
      });

      setActionSuccess(`تم عزل الوجبة ${quarantineTarget.batch_number} بنجاح إلى مستودع الحجر الصحي.`);
      setQuarantineTarget(null);
      refetch();
    } catch (err) {
      setActionError(err instanceof ApiError ? err.summary : "فشل نقل الوجبة للحجر الصحي.");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="flex flex-col gap-6">
      {actionSuccess && (
        <div className="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 border border-emerald-200">
          {actionSuccess}
        </div>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <StatCard
          label="إجمالي الوجبات المهددة"
          value={summary?.total_batches_at_risk ?? 0}
          icon={AlertCircle}
          tone="brand"
          hint="وجبات تنتهي خلال 180 يوم أو أقل"
        />
        <StatCard
          label="منتهية الصلاحية"
          value={summary?.expired_count ?? 0}
          icon={ShieldAlert}
          tone="red"
          hint={`القيمة: ${formatMoney(summary?.expired_value ?? 0)}`}
        />
        <StatCard
          label="حرجة (خلال 30 يوم)"
          value={summary?.critical_count ?? 0}
          icon={AlertTriangle}
          tone="red"
          hint={`القيمة: ${formatMoney(summary?.critical_value ?? 0)}`}
        />
        <StatCard
          label="تحذير (31 - 90 يوم)"
          value={summary?.warning_count ?? 0}
          icon={Clock}
          tone="amber"
          hint={`القيمة: ${formatMoney(summary?.warning_value ?? 0)}`}
        />
        <StatCard
          label="القيمة المالية المعرضة للهدر"
          value={formatMoney(summary?.total_financial_risk_value ?? 0)}
          icon={DollarSign}
          tone="blue"
          hint="إجمالي تكلفة الشراء للوجبات المهددة"
        />
      </div>

      {/* Filter Tabs */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-3">
        <div className="flex items-center gap-2">
          {[
            { key: "all", label: "الكل" },
            { key: "expired", label: "منتهية الصلاحية (Expired)" },
            { key: "critical", label: "حرجة <= 30 يوم" },
            { key: "warning", label: "تحذير 31-90 يوم" },
            { key: "notice", label: "تنبيه 91-180 يوم" },
          ].map((tab) => (
            <button
              key={tab.key}
              onClick={() => setFilterLevel(tab.key)}
              className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                filterLevel === tab.key
                  ? "bg-brand-600 text-white shadow-sm"
                  : "bg-slate-100 text-slate-600 hover:bg-slate-200"
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        <Button variant="outline" size="sm" onClick={() => refetch()} disabled={loading}>
          تحديث البيانات
        </Button>
      </div>

      {/* Data Table */}
      {loading ? (
        <div className="flex h-48 items-center justify-center">
          <Spinner size="lg" />
        </div>
      ) : error ? (
        <div className="rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</div>
      ) : items.length === 0 ? (
        <EmptyState
          title="لا توجد وجبات مهددة بالانتهاء"
          description="جميع الوجبات المخزنية في حالة آمنة وتاريخ صلاحيتها يتجاوز الفترة المحددة."
        />
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-card">
          <table className="min-w-full divide-y divide-slate-200 text-right text-sm">
            <thead className="bg-slate-50 text-xs font-semibold text-slate-600">
              <tr>
                <th className="px-4 py-3">المنتج / الاسم العلمي</th>
                <th className="px-4 py-3">رقم الوجبة</th>
                <th className="px-4 py-3">تاريخ الانتهاء</th>
                <th className="px-4 py-3">الأيام المتبقية</th>
                <th className="px-4 py-3">الكمية المتوفرة</th>
                <th className="px-4 py-3">القيمة التقديرية</th>
                <th className="px-4 py-3">المستودع</th>
                <th className="px-4 py-3">المورد</th>
                <th className="px-4 py-3 text-center">إجراءات الحجر والوقاية</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {items.map((item) => {
                let badgeTone: "red" | "amber" | "blue" | "slate" = "slate";
                let badgeText = `${item.days_to_expiry} يوم`;

                if (item.days_to_expiry < 0) {
                  badgeTone = "red";
                  badgeText = `منتهي منذ ${Math.abs(item.days_to_expiry)} يوم`;
                } else if (item.days_to_expiry <= 30) {
                  badgeTone = "red";
                  badgeText = `حرج (${item.days_to_expiry} يوم)`;
                } else if (item.days_to_expiry <= 90) {
                  badgeTone = "amber";
                  badgeText = `تحذير (${item.days_to_expiry} يوم)`;
                } else {
                  badgeTone = "blue";
                  badgeText = `تنبيه (${item.days_to_expiry} يوم)`;
                }

                const isQuarantineWarehouse = item.warehouse.type === "quarantine";

                return (
                  <tr key={item.stock_id} className="hover:bg-slate-50/70">
                    <td className="px-4 py-3 font-medium text-slate-900">
                      <div>{item.product.name}</div>
                      {item.product.generic_name && (
                        <div className="text-xs text-slate-400 font-normal">{item.product.generic_name}</div>
                      )}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs text-slate-600">{item.batch_number}</td>
                    <td className="px-4 py-3 text-slate-700">{item.expiry_date}</td>
                    <td className="px-4 py-3">
                      <Badge tone={badgeTone}>{badgeText}</Badge>
                    </td>
                    <td className="px-4 py-3 font-semibold text-slate-800">
                      {formatNumber(item.quantity_on_hand)}
                    </td>
                    <td className="px-4 py-3 text-slate-900 font-medium">
                      {formatMoney(item.total_risk_value)}
                    </td>
                    <td className="px-4 py-3">
                      <span className="text-xs font-medium text-slate-600">{item.warehouse.name}</span>
                      {isQuarantineWarehouse && (
                        <span className="mr-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800">
                          (محجور)
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-500">{item.supplier?.name ?? "—"}</td>
                    <td className="px-4 py-3 text-center">
                      {isQuarantineWarehouse ? (
                        <span className="text-xs text-slate-400">معزول بالفعل</span>
                      ) : (
                        <Button
                          size="sm"
                          variant="danger"
                          className="text-xs"
                          onClick={() => handleOpenQuarantine(item)}
                        >
                          <ArrowRightLeft className="ml-1 h-3.5 w-3.5" />
                          عزل للحجر الصحي
                        </Button>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Quarantine Modal */}
      {quarantineTarget && (
        <Modal
          open={!!quarantineTarget}
          onClose={() => setQuarantineTarget(null)}
          title="نقل الوجبة إلى مستودع الحجر الصحي (Quarantine)"
        >
          <form onSubmit={handleExecuteQuarantine} className="flex flex-col gap-4 text-right">
            <div className="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
              سيتم إنشاء حركة مخزنية رسمية لنقل هذه الوجبة إلى مستودع الحجر الصحي التابع لنفس الفرع، وذلك لمنع
              بيعها في شاشات الصيدلية لحين إرجاعها للمورد أو إتلافها.
            </div>

            {actionError && (
              <div className="rounded-lg bg-red-50 p-3 text-xs text-red-700">{actionError}</div>
            )}

            <div>
              <label className="text-xs font-medium text-slate-700">المنتج والوجبة:</label>
              <div className="mt-1 font-semibold text-slate-900">
                {quarantineTarget.product.name} — ({quarantineTarget.batch_number})
              </div>
            </div>

            <div>
              <label className="text-xs font-medium text-slate-700">الكمية المراد حجرها:</label>
              <input
                type="number"
                step="any"
                max={quarantineTarget.quantity_on_hand}
                min="0.001"
                required
                value={quarantineQty}
                onChange={(e) => setQuarantineQty(e.target.value)}
                className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
              />
              <p className="mt-1 text-[11px] text-slate-400">
                الكمية المتوفرة حالياً: {quarantineTarget.quantity_on_hand}
              </p>
            </div>

            <div>
              <label className="text-xs font-medium text-slate-700">ملاحظات وسبب الحجر:</label>
              <textarea
                rows={2}
                value={quarantineNotes}
                onChange={(e) => setQuarantineNotes(e.target.value)}
                className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
              />
            </div>

            <div className="mt-2 flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setQuarantineTarget(null)}>
                إلغاء
              </Button>
              <Button type="submit" variant="danger" disabled={submitting}>
                {submitting ? "جاري النقل..." : "تأكيد العزل إلى الحجر الصحي"}
              </Button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

