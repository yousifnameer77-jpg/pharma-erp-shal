// Client-side Excel/PDF export for report tables. Every report builds a
// small ExportColumn<T> array (a display label + a plain-string value
// getter) alongside its on-screen <DataTable> columns, and hands both the
// columns and the already-fetched rows to exportRowsToExcel/exportRowsToPdf
// below — no server round-trip, no server-side rendering.
"use client";

import { jsPDF } from "jspdf";
import autoTable from "jspdf-autotable";
import * as XLSX from "xlsx";

export interface ExportColumn<T> {
  header: string;
  value: (row: T) => string;
}

function rowsToAoa<T>(columns: ExportColumn<T>[], rows: T[]): string[][] {
  return rows.map((row) => columns.map((c) => c.value(row)));
}

/** Downloads `${filename}.xlsx` with one sheet built from `columns`/`rows`. */
export function exportRowsToExcel<T>(
  filename: string,
  sheetTitle: string,
  columns: ExportColumn<T>[],
  rows: T[],
) {
  const header = columns.map((c) => c.header);
  const body = rowsToAoa(columns, rows);
  const worksheet = XLSX.utils.aoa_to_sheet([header, ...body]);
  worksheet["!cols"] = columns.map(() => ({ wch: 18 }));

  const workbook = XLSX.utils.book_new();
  // Excel sheet names are capped at 31 characters and can't be empty.
  const safeSheetName = (sheetTitle || "Report").slice(0, 31);
  XLSX.utils.book_append_sheet(workbook, worksheet, safeSheetName);
  XLSX.writeFile(workbook, `${filename}.xlsx`);
}

/** Downloads `${filename}.pdf`: a title, a generated-at stamp, and one table. */
export function exportRowsToPdf<T>(
  filename: string,
  title: string,
  columns: ExportColumn<T>[],
  rows: T[],
) {
  const doc = new jsPDF({
    orientation: columns.length > 6 ? "landscape" : "portrait",
    unit: "pt",
  });

  doc.setFontSize(14);
  doc.text(title, 40, 40);
  doc.setFontSize(9);
  doc.setTextColor(120);
  doc.text(`Generated ${new Date().toLocaleString()}`, 40, 56);

  autoTable(doc, {
    startY: 68,
    head: [columns.map((c) => c.header)],
    body: rowsToAoa(columns, rows),
    styles: { fontSize: 8, cellPadding: 4 },
    headStyles: { fillColor: [74, 107, 245], textColor: 255 },
    alternateRowStyles: { fillColor: [248, 250, 252] },
    margin: { left: 40, right: 40 },
  });

  doc.save(`${filename}.pdf`);
}
