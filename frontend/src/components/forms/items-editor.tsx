"use client";

import { Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";

export interface ItemColumn<T> {
  key: string;
  header: string;
  render: (
    item: T,
    update: (patch: Partial<T>) => void,
    index: number,
  ) => React.ReactNode;
  width?: string;
}

/**
 * A generic "line items" table for document forms (purchase order lines,
 * invoice lines, journal entry lines, ...). Fully controlled — the caller
 * owns the item array and gets a full replacement back on every change.
 */
export function ItemsEditor<T>({
  items,
  onChange,
  newItem,
  columns,
  addLabel = "إضافة بند جديد",
  minItems = 1,
}: {
  items: T[];
  onChange: (items: T[]) => void;
  newItem: () => T;
  columns: ItemColumn<T>[];
  addLabel?: string;
  minItems?: number;
}) {
  function updateAt(index: number, patch: Partial<T>) {
    onChange(
      items.map((item, i) => (i === index ? { ...item, ...patch } : item)),
    );
  }

  function removeAt(index: number) {
    onChange(items.filter((_, i) => i !== index));
  }

  return (
    <div className="col-span-2">
      <div className="overflow-x-auto rounded-lg border border-slate-200">
        <table className="w-full min-w-[560px] border-collapse text-sm">
          <thead>
            <tr className="border-b border-slate-200 bg-slate-50/60">
              {columns.map((col) => (
                <th
                  key={col.key}
                  style={{ width: col.width }}
                  className="whitespace-nowrap px-3 py-2 text-start text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                  {col.header}
                </th>
              ))}
              <th className="w-10" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {items.map((item, index) => (
              <tr key={index}>
                {columns.map((col) => (
                  <td key={col.key} className="px-3 py-2 align-top">
                    {col.render(item, (patch) => updateAt(index, patch), index)}
                  </td>
                ))}
                <td className="px-2 py-2 text-right align-top">
                  {items.length > minItems && (
                    <button
                      type="button"
                      onClick={() => removeAt(index)}
                      className="rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-500"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  )}
                </td>
              </tr>
            ))}
            {items.length === 0 && (
              <tr>
                <td
                  colSpan={columns.length + 1}
                  className="px-3 py-4 text-center text-xs text-slate-400"
                >
                  لا توجد بنود مضافة بعد.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="mt-2"
        onClick={() => onChange([...items, newItem()])}
      >
        <Plus className="h-3.5 w-3.5" /> {addLabel}
      </Button>
    </div>
  );
}

export const inlineInputClasses =
  "h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-sm focus:outline-none focus:ring-1 focus:ring-brand-500 focus:border-brand-500";
