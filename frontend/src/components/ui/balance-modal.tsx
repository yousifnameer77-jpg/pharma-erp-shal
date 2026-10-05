"use client";

import { Modal } from "@/components/ui/modal";
import { LoadingBlock } from "@/components/ui/spinner";
import { formatMoney } from "@/lib/format";

export function BalanceModal({
  open,
  onClose,
  title,
  loading,
  rows,
  balance,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  loading: boolean;
  rows: { label: string; value: number }[];
  balance: number;
}) {
  return (
    <Modal open={open} onClose={onClose} title={title} size="sm">
      {loading ? (
        <LoadingBlock />
      ) : (
        <div className="divide-y divide-slate-100">
          {rows.map((r) => (
            <div
              key={r.label}
              className="flex items-center justify-between py-2 text-sm"
            >
              <span className="text-slate-500">{r.label}</span>
              <span className="font-medium text-slate-800">
                {formatMoney(r.value)}
              </span>
            </div>
          ))}
          <div className="flex items-center justify-between py-2.5 text-sm">
            <span className="font-semibold text-slate-900">
              الرصيد المتبقي المستحق
            </span>
            <span
              className={`text-base font-semibold ${balance > 0 ? "text-red-600" : "text-emerald-600"}`}
            >
              {formatMoney(balance)}
            </span>
          </div>
        </div>
      )}
    </Modal>
  );
}
