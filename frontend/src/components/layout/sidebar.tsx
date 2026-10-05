"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Pill, X } from "lucide-react";
import { cn } from "@/lib/cn";
import { useAuth } from "@/lib/auth-context";
import { NAV_ITEMS } from "@/components/layout/nav-config";

function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();
  const { hasPermission, user } = useAuth();

  const isSuperAdmin =
    user?.roles?.some((r) => r.name === "Super Admin") ?? false;

  return (
    <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto px-3 py-2">
      {NAV_ITEMS.filter(
        (item) =>
          !item.permission || isSuperAdmin || hasPermission(item.permission),
      ).map((item) => {
        const active =
          pathname === item.href || pathname?.startsWith(`${item.href}/`);
        const Icon = item.icon;
        return (
          <Link
            key={item.href}
            href={item.href}
            onClick={onNavigate}
            className={cn(
              "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
              active
                ? "bg-brand-50 text-brand-700"
                : "text-slate-600 hover:bg-slate-100 hover:text-slate-900",
            )}
          >
            <Icon
              className={cn(
                "h-4 w-4 shrink-0",
                active ? "text-brand-600" : "text-slate-400",
              )}
            />
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}

function Brand() {
  return (
    <div className="flex h-14 shrink-0 items-center gap-2.5 border-b border-slate-200 px-4">
      <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-700 text-white shadow-sm">
        <Pill className="h-5 w-5" />
      </div>
      <div className="min-w-0 leading-tight">
        <p className="truncate text-sm font-bold text-slate-900 tracking-tight">سهل الحضارات</p>
        <p className="truncate text-[11px] font-medium text-emerald-700">
          أدوية بيطرية • ERP
        </p>
      </div>
    </div>
  );
}

export function Sidebar() {
  return (
    <aside className="hidden w-64 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex">
      <Brand />
      <NavLinks />
    </aside>
  );
}

export function MobileSidebar({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex lg:hidden">
      <div
        className="fixed inset-0 bg-slate-900/50"
        onClick={onClose}
        aria-hidden
      />
      <div className="relative flex w-64 flex-col bg-white shadow-xl">
        <div className="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 px-4">
          <div className="flex items-center gap-2.5">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-700 text-white">
              <Pill className="h-4 w-4" />
            </div>
            <div>
              <p className="text-sm font-bold text-slate-900">سهل الحضارات</p>
              <p className="text-[10px] text-emerald-700">أدوية بيطرية</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="rounded-md p-1 text-slate-400 hover:bg-slate-100"
            aria-label="Close menu"
          >
            <X className="h-4 w-4" />
          </button>
        </div>
        <NavLinks onNavigate={onClose} />
      </div>
    </div>
  );
}
