"use client";

import { FileSpreadsheet, FileText, Printer } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  exportRowsToExcel,
  exportRowsToPdf,
  type ExportColumn,
} from "@/lib/export";

export type { ExportColumn };

/** Excel / PDF / Print actions for a report table — reused across every report tab. */
export function ReportExportBar<T>({
  title,
  filename,
  columns,
  rows,
  extra,
}: {
  /** Shown as the PDF's heading and used as the Excel sheet name. */
  title: string;
  /** Base filename, no extension — `${filename}.xlsx` / `${filename}.pdf`. */
  filename: string;
  columns: ExportColumn<T>[];
  rows: T[];
  /** Extra controls (e.g. a date picker) rendered to the left of the export buttons. */
  extra?: React.ReactNode;
}) {
  const disabled = rows.length === 0;

  return (
    <div className="no-print flex flex-wrap items-center justify-between gap-3">
      <div className="flex flex-wrap items-end gap-3">{extra}</div>
      <div className="flex flex-wrap items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          disabled={disabled}
          onClick={() => exportRowsToExcel(filename, title, columns, rows)}
        >
          <FileSpreadsheet className="h-4 w-4" /> تصدير إكسل
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={disabled}
          onClick={() => exportRowsToPdf(filename, title, columns, rows)}
        >
          <FileText className="h-4 w-4" /> تصدير PDF
        </Button>
        <Button variant="outline" size="sm" onClick={() => window.print()}>
          <Printer className="h-4 w-4" /> طباعة فورية
        </Button>
      </div>
    </div>
  );
}
