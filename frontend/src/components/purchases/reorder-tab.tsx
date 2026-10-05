"use client";

import { useState } from "react";
import { RefreshCw, ShoppingBag, DollarSign, AlertTriangle, CheckSquare, Square } from "lucide-react";
import { StatCard } from "@/components/ui/stat-card";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { EmptyState } from "@/components/ui/empty-state";
import { useApiResource } from "@/lib/hooks";
import { api, ApiError } from "@/lib/api";
import { formatMoney, formatNumber } from "@/lib/format";

interface ReorderItem {
  product_id: string;
  name: string;
  generic_name: string | null;
  code: string;
  barcode: string | null;
  category: string | null;
  manufacturer: string | null;
  current_stock: number;
  min_stock_level: number;
  reorder_point: number;
  suggested_quantity: number;
  unit_cost: number;
  estimated_total_cost: number;
  last_supplier: {
    id: string;
    name: string;
  } | null;
}

interface ReorderData {
  summary: {
    total_products_below_min: number;
    total_suggested_units: number;
    estimated_total_reorder_cost: number;
  };
  items: ReorderItem[];
}

export function ReorderTab({ companyId }: { companyId: string }) {
  const { data, loading, error, refetch } = useApiResource<ReorderData>(
    "/v1/purchasing/reorder-suggestions"
  );

  const [selectedIds, setSelectedIds] = useState<Record<string, boolean>>({});
  const [quantities, setQuantities] = useState<Record<string, number>>({});
  const [submitting, setSubmitting] = useState(false);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const items = data?.items ?? [];
  const summary = data?.summary;

  const handleToggleSelect = (id: string) => {
    setSelectedIds((prev) => ({ ...prev, [id]: !prev[id] }));
  };

  const handleSelectAll = () => {
    const allSelected = items.length > 0 && items.every((item) => selectedIds[item.product_id]);
    const nextState: Record<string, boolean> = {};
    if (!allSelected) {
      items.forEach((item) => {
        nextState[item.product_id] = true;
      });
    }
    setSelectedIds(nextState);
  };

  const handleQtyChange = (productId: string, val: number) => {
    setQuantities((prev) => ({ ...prev, [productId]: Math.max(1, val) }));
  };

  const handleGeneratePurchaseRequest = async () => {
    const selectedItems = items.filter((item) => selectedIds[item.product_id]);
    if (selectedItems.length === 0) {
      setActionError("يرجى تحديد منتج واحد على الأقل لتوليد طلب الشراء.");
      return;
    }

    setSubmitting(true);
    setActionError(null);
    setSuccessMsg(null);

    try {
      const payload = {
        notes: "تم التوليد تلقائياً بواسطة محرك إعادة الطلب الذكي (Auto Re-order)",
        items: selectedItems.map((item) => ({
          product_id: item.product_id,
          quantity: quantities[item.product_id] ?? item.suggested_quantity,
          notes: `إعادة تعويض المخزون (الرصيد الحالي: ${item.current_stock})`,
        })),
      };

      const res = await api.post<{ message: string; data: { request_number: string } }>(
        "/v1/purchasing/reorder-suggestions/create-request",
        payload
      );

      setSuccessMsg(
        `تم بنجاح إنشاء مسودة طلب الشراء رقم (${res.data.request_number}) لعدد ${selectedItems.length} أدوية! يمكنك مراجعته الآن في تبويب الطلبات (Requests).`
      );
      setSelectedIds({});
      refetch();
    } catch (err) {
      setActionError(err instanceof ApiError ? err.summary : "فشل إنشاء طلب الشراء التلقائي.");
    } finally {
      setSubmitting(false);
    }
  };

  const selectedCount = Object.values(selectedIds).filter(Boolean).length;

  return (
    <div className="flex flex-col gap-6">
      {successMsg && (
        <div className="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 border border-emerald-200">
          {successMsg}
        </div>
      )}

      {actionError && (
        <div className="rounded-lg bg-red-50 p-4 text-sm text-red-700 border border-red-200">
          {actionError}
        </div>
      )}

      {/* KPI Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
          label="أدوية تحت الحد الأدنى / نقطة الطلب"
          value={summary?.total_products_below_min ?? 0}
          icon={AlertTriangle}
          tone="red"
          hint="أدوية تحتاج إعادة تموين عاجلة"
        />
        <StatCard
          label="إجمالي الوحدات المقترحة للشراء"
          value={formatNumber(summary?.total_suggested_units ?? 0)}
          icon={ShoppingBag}
          tone="amber"
          hint="كمية التموين لتأمين المخزون"
        />
        <StatCard
          label="التكلفة التقديرية لإعادة الطلب"
          value={formatMoney(summary?.estimated_total_reorder_cost ?? 0)}
          icon={DollarSign}
          tone="brand"
          hint="استناداً إلى آخر أسعار شراء مسجلة"
        />
      </div>

      {/* Actions Bar */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-3">
        <div className="flex items-center gap-3">
          <Button
            variant="outline"
            size="sm"
            onClick={handleSelectAll}
            disabled={loading || items.length === 0}
          >
            {items.length > 0 && items.every((i) => selectedIds[i.product_id]) ? (
              <>
                <CheckSquare className="ml-1.5 h-4 w-4 text-brand-600" />
                إلغاء تحديد الكل
              </>
            ) : (
              <>
                <Square className="ml-1.5 h-4 w-4" />
                تحديد جميع الأدوية ({items.length})
              </>
            )}
          </Button>

          {selectedCount > 0 && (
            <span className="text-xs font-semibold text-brand-700 bg-brand-50 px-2.5 py-1 rounded-full border border-brand-200">
              تم تحديد {selectedCount} دواء
            </span>
          )}
        </div>

        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => refetch()} disabled={loading}>
            <RefreshCw className="ml-1 h-3.5 w-3.5" />
            تحديث
          </Button>

          <Button
            variant="primary"
            size="sm"
            onClick={handleGeneratePurchaseRequest}
            disabled={submitting || selectedCount === 0}
          >
            {submitting ? "جاري التوليد..." : `⚡ توليد طلب شراء للأدوية المحددة (${selectedCount})`}
          </Button>
        </div>
      </div>

      {/* Table */}
      {loading ? (
        <div className="flex h-48 items-center justify-center">
          <Spinner className="h-8 w-8" />
        </div>
      ) : error ? (
        <div className="rounded-lg bg-red-50 p-4 text-sm text-red-700">{error}</div>
      ) : items.length === 0 ? (
        <EmptyState
          title="مستويات المخزون ممتازة!"
          description="لا توجد أدوية حالياً تحت الحد الأدنى أو بحاجة إلى إعادة طلب."
        />
      ) : (
        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-card">
          <table className="min-w-full divide-y divide-slate-200 text-right text-sm">
            <thead className="bg-slate-50 text-xs font-semibold text-slate-600">
              <tr>
                <th className="w-10 px-4 py-3 text-center">اختيار</th>
                <th className="px-4 py-3">الدواء / الاسم العلمي</th>
                <th className="px-4 py-3">الكود / الباركود</th>
                <th className="px-4 py-3">المخزون الحالي</th>
                <th className="px-4 py-3">الحد الأدنى / نقطة الطلب</th>
                <th className="px-4 py-3">الكمية المقترحة للطلب</th>
                <th className="px-4 py-3">سعر الشراء المتوقع</th>
                <th className="px-4 py-3">إجمالي التكلفة</th>
                <th className="px-4 py-3">آخر مورد معتمد</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {items.map((item) => {
                const isSelected = !!selectedIds[item.product_id];
                const orderQty = quantities[item.product_id] ?? item.suggested_quantity;
                const totalLineCost = orderQty * item.unit_cost;

                return (
                  <tr
                    key={item.product_id}
                    className={`transition ${isSelected ? "bg-brand-50/50" : "hover:bg-slate-50/70"}`}
                  >
                    <td className="px-4 py-3 text-center">
                      <input
                        type="checkbox"
                        checked={isSelected}
                        onChange={() => handleToggleSelect(item.product_id)}
                        className="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 cursor-pointer"
                      />
                    </td>
                    <td className="px-4 py-3 font-medium text-slate-900">
                      <div>{item.name}</div>
                      {item.generic_name && (
                        <div className="text-xs text-slate-400 font-normal">{item.generic_name}</div>
                      )}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs text-slate-600">{item.code}</td>
                    <td className="px-4 py-3">
                      <span className="font-bold text-red-600">{formatNumber(item.current_stock)}</span>
                      <span className="mr-1 text-xs text-slate-400">قطعة</span>
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-600">
                      <div>الحد الأدنى: {formatNumber(item.min_stock_level)}</div>
                      <div>نقطة الطلب: {formatNumber(item.reorder_point)}</div>
                    </td>
                    <td className="px-4 py-3">
                      <input
                        type="number"
                        min="1"
                        value={orderQty}
                        onChange={(e) =>
                          handleQtyChange(item.product_id, parseInt(e.target.value) || 1)
                        }
                        className="w-24 rounded-lg border border-slate-300 px-2.5 py-1 text-sm font-semibold text-slate-900 text-center focus:border-brand-500 focus:outline-none"
                      />
                    </td>
                    <td className="px-4 py-3 text-slate-700">{formatMoney(item.unit_cost)}</td>
                    <td className="px-4 py-3 font-semibold text-slate-900">
                      {formatMoney(totalLineCost)}
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-500">
                      {item.last_supplier?.name ?? "مورد عام"}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

