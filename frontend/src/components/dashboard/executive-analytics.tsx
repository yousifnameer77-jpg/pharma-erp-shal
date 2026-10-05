"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  AreaChart,
  Area,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import {
  TrendingUp,
  Monitor,
  ShieldAlert,
  Clock,
  RotateCw,
  Sparkles,
  DollarSign,
  ArrowUpRight,
  PieChart as PieIcon,
  CreditCard,
  Wallet,
  Coins,
  Landmark,
} from "lucide-react";
import { useApiResource } from "@/lib/hooks";
import { useAuth } from "@/lib/auth-context";
import { PERMISSIONS } from "@/lib/permissions";

export function ExecutiveAnalytics() {
  const { data, loading } = useApiResource<any>("/v1/analytics/executive");

  if (loading && !data) {
    return (
      <div className="h-44 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl animate-pulse flex items-center justify-center text-xs text-slate-400 font-bold">
        جاري تحميل التحليلات المالية والمبيعات...
      </div>
    );
  }

  if (!data) return null;

  const { kpis, sales_trend, top_products, payment_split } = data;

  const { user, hasPermission } = useAuth();
  const isSuperAdmin = user?.roles?.some((r) => r.name === "Super Admin") ?? false;
  const can = (p: string) => isSuperAdmin || hasPermission(p);

  const actions = [
    can(PERMISSIONS.salesCreate) && {
      href: "/pos",
      gradient: "from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600",
      sub: "المبيعات المباشرة",
      title: "نقطة البيع (POS)",
      subColor: "text-emerald-100",
      icon: Monitor,
    },
    can(PERMISSIONS.productsManage) && {
      href: "/controlled-drugs",
      gradient: "from-rose-600 to-red-700 hover:from-rose-500 hover:to-red-600",
      sub: "الرقابة والتفتيش",
      title: "الأدوية المراقبة",
      subColor: "text-rose-100",
      icon: ShieldAlert,
    },
    can(PERMISSIONS.reportsView) && {
      href: "/reports",
      gradient: "from-amber-600 to-orange-700 hover:from-amber-500 hover:to-orange-600",
      sub: "فحص الصلاحيات",
      title: "الحجر الصحي للأدوية",
      subColor: "text-amber-100",
      icon: Clock,
    },
    can(PERMISSIONS.purchasingView) && {
      href: "/purchases",
      gradient: "from-cyan-600 to-blue-700 hover:from-cyan-500 hover:to-blue-600",
      sub: "النواقص التلقائية",
      title: "إعادة الطلب الذكي",
      subColor: "text-cyan-100",
      icon: RotateCw,
    },
    can(PERMISSIONS.financeJournalView) && {
      href: "/accounting",
      gradient: "from-indigo-600 to-blue-700 hover:from-indigo-500 hover:to-blue-600",
      sub: "دفتر اليومية والقيود",
      title: "الحسابات العامة",
      subColor: "text-indigo-100",
      icon: Landmark,
    },
    can(PERMISSIONS.financeManage) && {
      href: "/accounting",
      gradient: "from-violet-600 to-purple-700 hover:from-violet-500 hover:to-purple-600",
      sub: "الذمم والأرصدة",
      title: "دليل وشجرة الحسابات",
      subColor: "text-violet-100",
      icon: Wallet,
    },
  ].filter(Boolean) as Array<{
    href: string;
    gradient: string;
    sub: string;
    title: string;
    subColor: string;
    icon: any;
  }>;

  return (
    <div className="space-y-5" dir="rtl">
      
      {/* Quick Action Operational Hub */}
      {actions.length > 0 && (
        <div className={`grid grid-cols-2 sm:grid-cols-${Math.min(actions.length, 4)} gap-4`}>
          {actions.map((act, idx) => {
            const IconComp = act.icon;
            return (
              <Link
                key={idx}
                href={act.href}
                className={`group relative flex flex-col justify-between min-h-[120px] overflow-hidden bg-gradient-to-br ${act.gradient} text-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 transform hover:-translate-y-0.5`}
              >
                <div className="flex items-center justify-between">
                  <div className="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                    <IconComp className="w-5 h-5 text-white" />
                  </div>
                  <ArrowUpRight className="w-4 h-4 opacity-75 group-hover:opacity-100 transition" />
                </div>
                <div className="mt-3">
                  <span className={`block text-xs font-medium ${act.subColor} mb-0.5`}>
                    {act.sub}
                  </span>
                  <span className="text-base font-bold text-white block leading-snug">
                    {act.title}
                  </span>
                </div>
              </Link>
            );
          })}
        </div>
      )}

      {/* Main Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        {/* Sales & Profit 7-Day Area Chart (2 Columns) */}
        <div className="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm">
          <div className="flex items-center justify-between mb-4">
            <div>
              <div className="flex items-center gap-2">
                <h3 className="font-extrabold text-sm text-slate-900 dark:text-white">
                  حركة المبيعات وصافي الأرباح (آخر 7 أيام)
                </h3>
                <span className="text-[10px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold px-2 py-0.5 rounded-full">
                  هامش الربح: {kpis?.profit_margin}%
                </span>
              </div>
              <p className="text-xs text-slate-400 mt-0.5">
                مقارنة الإيرادات اليومية المحققة مقابل هامش الربح التشغيلي
              </p>
            </div>

            <div className="flex items-center gap-3 text-xs font-bold">
              <span className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                المبيعات
              </span>
              <span className="flex items-center gap-1.5 text-cyan-600 dark:text-cyan-400">
                <span className="w-2.5 h-2.5 rounded-full bg-cyan-500"></span>
                الأرباح
              </span>
            </div>
          </div>

          <div className="h-64 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={sales_trend} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                <defs>
                  <linearGradient id="colorSales" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#10B981" stopOpacity={0.4} />
                    <stop offset="95%" stopColor="#10B981" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="colorProfit" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#06B6D4" stopOpacity={0.4} />
                    <stop offset="95%" stopColor="#06B6D4" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#334155" opacity={0.2} />
                <XAxis dataKey="day" tick={{ fontSize: 11, fill: "#94a3b8" }} axisLine={false} tickLine={false} />
                <YAxis tick={{ fontSize: 11, fill: "#94a3b8" }} axisLine={false} tickLine={false} />
                <Tooltip
                  formatter={(val: number) => [`${val.toLocaleString()} د.ع`]}
                  contentStyle={{
                    borderRadius: "16px",
                    border: "1px solid #334155",
                    backgroundColor: "#0f172a",
                    color: "#fff",
                    fontSize: "12px",
                  }}
                />
                <Area type="monotone" dataKey="sales" stroke="#10B981" strokeWidth={3} fillOpacity={1} fill="url(#colorSales)" name="المبيعات" />
                <Area type="monotone" dataKey="profit" stroke="#06B6D4" strokeWidth={3} fillOpacity={1} fill="url(#colorProfit)" name="الأرباح" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Top 5 Best Selling Medicines Ranking (1 Column) */}
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-3">
              <h3 className="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-1.5">
                <Sparkles className="w-4 h-4 text-amber-500" />
                الأدوية الأكثر طلباً وربحاً
              </h3>
              <span className="text-[10px] text-slate-400 font-bold">Top 5</span>
            </div>

            <div className="space-y-3">
              {!top_products?.length && (
                <p className="py-6 text-center text-xs text-slate-400">لا توجد مبيعات مسجّلة بعد.</p>
              )}
              {top_products?.map((item: any, idx: number) => (
                <div key={idx} className="flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2.5 min-w-0">
                    <span className="w-5 h-5 rounded-lg bg-slate-100 dark:bg-slate-800 font-bold text-[10px] flex items-center justify-center text-slate-500 shrink-0">
                      {idx + 1}
                    </span>
                    <div className="truncate">
                      <span className="font-bold text-slate-800 dark:text-slate-200 block truncate">
                        {item.name}
                      </span>
                      <span className="text-[10px] text-slate-400">{item.form}</span>
                    </div>
                  </div>

                  <div className="text-left shrink-0">
                    <span className="font-black text-emerald-600 dark:text-emerald-400 font-mono block">
                      {Number(item.revenue ?? 0).toLocaleString()} د.ع
                    </span>
                    <span className="text-[10px] text-slate-400">
                      {item.sales_count} فاتورة
                    </span>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Payment Split Preview */}
          <div className="mt-4 pt-3.5 border-t border-slate-200 dark:border-slate-800">
            <div className="flex items-center justify-between text-xs font-bold mb-2">
              <span className="text-slate-700 dark:text-slate-300">طرق الدفع والتحصيل</span>
              <span className="text-slate-400 text-[10px]">
                {payment_split?.length
                  ? payment_split.map((p: any) => `${p.name} ${p.value}%`).join(" • ")
                  : "لا توجد مبيعات بعد"}
              </span>
            </div>
            <div className="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden flex">
              {payment_split?.map((p: any) => (
                <div key={p.name} style={{ width: `${p.value}%`, backgroundColor: p.color }} className="h-full"></div>
              ))}
            </div>
          </div>
        </div>

      </div>

    </div>
  );
}

