import { Inbox } from "lucide-react";
import type { LucideIcon } from "lucide-react";

export function EmptyState({
  title = "لا توجد بيانات مسجلة حالياً",
  description,
  icon: Icon = Inbox,
  action,
}: {
  title?: string;
  description?: string;
  icon?: LucideIcon;
  action?: React.ReactNode;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
      <div className="rounded-full bg-slate-100 p-3">
        <Icon className="h-5 w-5 text-slate-400" />
      </div>
      <p className="text-sm font-medium text-slate-700">{title}</p>
      {description && (
        <p className="max-w-sm text-xs text-slate-400">{description}</p>
      )}
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}

export function ErrorState({
  message,
  onRetry,
}: {
  message: string;
  onRetry?: () => void;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
      <p className="text-sm font-medium text-red-600">{message}</p>
      {onRetry && (
        <button
          onClick={onRetry}
          className="text-xs font-medium text-brand-600 underline underline-offset-2 hover:text-brand-700"
        >
          إعادة المحاولة
        </button>
      )}
    </div>
  );
}
