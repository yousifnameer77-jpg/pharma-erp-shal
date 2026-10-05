import type { LucideIcon } from "lucide-react";
import { cn } from "@/lib/cn";

export function StatCard({
  label,
  value,
  icon: Icon,
  tone = "brand",
  hint,
}: {
  label: string;
  value: React.ReactNode;
  icon?: LucideIcon;
  tone?: "brand" | "emerald" | "amber" | "red" | "slate" | "blue";
  hint?: string;
}) {
  const toneClasses: Record<string, string> = {
    brand: "bg-brand-50 text-brand-600",
    emerald: "bg-emerald-50 text-emerald-600",
    amber: "bg-amber-50 text-amber-600",
    red: "bg-red-50 text-red-600",
    slate: "bg-slate-100 text-slate-600",
    blue: "bg-blue-50 text-blue-600",
  };

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-card">
      <div className="flex items-start justify-between">
        <div>
          <p className="text-xs font-medium text-slate-500">{label}</p>
          <p className="mt-1.5 text-2xl font-semibold tracking-tight text-slate-900">
            {value}
          </p>
          {hint && <p className="mt-1 text-xs text-slate-400">{hint}</p>}
        </div>
        {Icon && (
          <div className={cn("rounded-lg p-2", toneClasses[tone])}>
            <Icon className="h-4 w-4" />
          </div>
        )}
      </div>
    </div>
  );
}
