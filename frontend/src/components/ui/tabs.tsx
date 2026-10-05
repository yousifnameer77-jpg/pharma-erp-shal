"use client";

import { cn } from "@/lib/cn";

export interface TabItem {
  key: string;
  label: string;
  icon?: React.ReactNode;
  count?: number;
}

export function Tabs({
  tabs,
  active,
  onChange,
}: {
  tabs: TabItem[];
  active: string;
  onChange: (key: string) => void;
}) {
  return (
    <div className="-mb-px flex gap-1 overflow-x-auto border-b border-slate-200">
      {tabs.map((tab) => {
        const isActive = tab.key === active;
        return (
          <button
            key={tab.key}
            onClick={() => onChange(tab.key)}
            className={cn(
              "flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors",
              isActive
                ? "border-brand-600 text-brand-700"
                : "border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700",
            )}
          >
            {tab.icon}
            {tab.label}
            {tab.count !== undefined && (
              <span
                className={cn(
                  "ml-0.5 rounded-full px-1.5 py-0.5 text-xs",
                  isActive
                    ? "bg-brand-50 text-brand-700"
                    : "bg-slate-100 text-slate-500",
                )}
              >
                {tab.count}
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}
