"use client";

import { ShieldAlert } from "lucide-react";
import { useAuth } from "@/lib/auth-context";

/** Guards a whole page behind a permission code — mirrors the backend's `permission:*` middleware. */
export function PermissionGate({
  permission,
  children,
}: {
  permission: string;
  children: React.ReactNode;
}) {
  const { hasPermission, user } = useAuth();
  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;

  if (!isSuperAdmin && !hasPermission(permission)) {
    return (
      <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 py-20 px-6 text-center shadow-card" dir="rtl">
        <div className="rounded-full bg-red-50 dark:bg-red-950/50 p-4 ring-8 ring-red-50/50 dark:ring-red-950/20">
          <ShieldAlert className="h-8 w-8 text-red-600 dark:text-red-400" />
        </div>
        <div className="max-w-md">
          <h3 className="text-base font-bold text-slate-800 dark:text-slate-100 mb-1">
            عذراً، ليس لديك الصلاحية للوصول إلى هذه الشاشة
          </h3>
          <p className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mb-3">
            تم تقييد الوصول لهذه الصفحة وفقاً لمسؤوليات حسابك المالي والوظيفي. يرجى مراجعة إدارة النظام إذا كنت بحاجة لصلاحيات إضافية.
          </p>
          <span className="inline-block text-[11px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700">
            الصلاحية المطلوبة: {permission}
          </span>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
