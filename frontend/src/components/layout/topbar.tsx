"use client";

import { useState } from "react";
import { Menu, ChevronDown, LogOut, Building2 } from "lucide-react";
import { useAuth } from "@/lib/auth-context";
import { cn } from "@/lib/cn";

export function Topbar({ onMenuClick }: { onMenuClick: () => void }) {
  const { user, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);

  const initials = (user?.full_name ?? user?.username ?? "?")
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();

  return (
    <header className="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4">
      <button
        onClick={onMenuClick}
        className="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
        aria-label="Open menu"
      >
        <Menu className="h-5 w-5" />
      </button>

      {user?.branch && (
        <div className="hidden items-center gap-1.5 text-xs text-slate-400 sm:flex">
          <Building2 className="h-3.5 w-3.5" />
          {user.branch.name}
        </div>
      )}

      <div className="relative ml-auto">
        <button
          onClick={() => setMenuOpen((v) => !v)}
          className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-slate-50"
        >
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
            {initials}
          </span>
          <span className="hidden text-sm sm:block">
            <span className="block font-medium text-slate-800 leading-tight">
              {user?.full_name}
            </span>
            <span className="block text-xs text-slate-400 leading-tight">
              {user?.roles?.[0]?.name ?? user?.username}
            </span>
          </span>
          <ChevronDown className="h-3.5 w-3.5 text-slate-400" />
        </button>

        {menuOpen && (
          <>
            <div
              className="fixed inset-0 z-10"
              onClick={() => setMenuOpen(false)}
            />
            <div
              className={cn(
                "absolute left-0 z-20 mt-1 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg",
              )}
            >
              <div className="border-b border-slate-100 px-3 py-2 text-right">
                <p className="text-sm font-medium text-slate-800">
                  {user?.full_name}
                </p>
                <p className="text-xs text-slate-400">
                  {user?.email ?? user?.username}
                </p>
              </div>
              <button
                onClick={() => {
                  setMenuOpen(false);
                  logout();
                }}
                className="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-slate-50 text-right"
              >
                <LogOut className="h-4 w-4" />
                تسجيل الخروج
              </button>
            </div>
          </>
        )}
      </div>
    </header>
  );
}
