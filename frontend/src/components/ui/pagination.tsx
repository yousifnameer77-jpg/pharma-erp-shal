import { ChevronLeft, ChevronRight } from "lucide-react";
import type { PaginationMeta } from "@/lib/types";

export function Pagination({
  meta,
  page,
  onPageChange,
  totalPages,
}: {
  meta?: PaginationMeta | null;
  page: number;
  onPageChange: (page: number) => void;
  totalPages?: number;
}) {
  const lastPage = totalPages ?? meta?.last_page ?? 1;
  if (lastPage <= 1) return null;

  return (
    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
      <span>
        {meta ? (
          <>
            عرض{" "}
            <span className="font-medium text-slate-700">{meta.from ?? 0}</span>–
            <span className="font-medium text-slate-700">{meta.to ?? 0}</span> من إجمالي{" "}
            <span className="font-medium text-slate-700">{meta.total}</span>
          </>
        ) : (
          <span>
            الصفحة {page} من {lastPage}
          </span>
        )}
      </span>
      <div className="flex items-center gap-1">
        <button
          onClick={() => onPageChange(page - 1)}
          disabled={page <= 1}
          className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-40"
          aria-label="الصفحة السابقة"
        >
          <ChevronRight className="h-3.5 w-3.5" />
        </button>
        <span className="px-2 font-medium text-slate-700">
          {page} / {lastPage}
        </span>
        <button
          onClick={() => onPageChange(page + 1)}
          disabled={page >= lastPage}
          className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-40"
          aria-label="الصفحة التالية"
        >
          <ChevronLeft className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  );
}
