import { cn } from "@/lib/cn";
import { statusTone, formatStatus } from "@/lib/format";

const toneClasses: Record<string, string> = {
  slate: "bg-slate-100 text-slate-700 ring-slate-600/10",
  amber: "bg-amber-50 text-amber-700 ring-amber-600/20",
  blue: "bg-blue-50 text-blue-700 ring-blue-700/10",
  emerald: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
  red: "bg-red-50 text-red-700 ring-red-600/10",
  violet: "bg-violet-50 text-violet-700 ring-violet-700/10",
};

export function Badge({
  children,
  tone = "slate",
  className,
}: {
  children: React.ReactNode;
  tone?: keyof typeof toneClasses;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset",
        toneClasses[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}

/** Convenience wrapper for the common case: a workflow `status` string field. */
export function StatusBadge({
  status,
  className,
}: {
  status: string;
  className?: string;
}) {
  return (
    <Badge tone={statusTone(status)} className={className}>
      {formatStatus(status)}
    </Badge>
  );
}
