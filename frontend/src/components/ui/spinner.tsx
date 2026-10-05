import { Loader2 } from "lucide-react";
import { cn } from "@/lib/cn";

export function Spinner({
  className,
  size = "md",
}: {
  className?: string;
  size?: "sm" | "md" | "lg" | string;
}) {
  const sizeClass =
    size === "sm" ? "h-4 w-4" : size === "lg" ? "h-8 w-8" : "h-5 w-5";
  return (
    <Loader2
      className={cn("animate-spin text-brand-600", sizeClass, className)}
    />
  );
}

export function LoadingBlock({ label = "Loading…" }: { label?: string }) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-16 text-slate-400">
      <Spinner />
      <span className="text-xs">{label}</span>
    </div>
  );
}
