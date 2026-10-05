/**
 * The backend serializes every `decimal:N`-cast column as a JSON *string*
 * (e.g. "total_amount": "1050.000") so PHP never loses precision on the way
 * out — see api.ts's header comment. These helpers turn that back into
 * something the UI can compute/format with, and back again for form inputs.
 */
export function toNumber(value: string | number | null | undefined): number {
  if (value === null || value === undefined || value === "") return 0;
  const n = typeof value === "number" ? value : parseFloat(value);
  return Number.isFinite(n) ? n : 0;
}

export function formatMoney(
  value: string | number | null | undefined,
  currency = "د.ع",
): string {
  const n = toNumber(value);
  const formatted = n.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
  return currency ? `${formatted} ${currency}` : formatted;
}

export function formatNumber(
  value: string | number | null | undefined,
  decimals = 2,
): string {
  const n = toNumber(value);
  return n.toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: decimals,
  });
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return "—";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString("en-CA"); // YYYY-MM-DD
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return "—";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleString("en-GB", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

/** Today's date as YYYY-MM-DD, for defaulting <input type="date"> fields. */
export function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

export function titleCase(value: string | null | undefined): string {
  if (!value) return "—";
  return String(value)
    .split(/[_\s]+/)
    .map((w) => (w ? w[0].toUpperCase() + w.slice(1) : w))
    .join(" ");
}

const STATUS_ARABIC: Record<string, string> = {
  draft: "مسودة",
  submitted: "مرسلة",
  approved: "معتمدة",
  partially_received: "مستلمة جزئياً",
  received: "مستلمة بالكامل",
  posted: "مرحّلة",
  partially_paid: "مسددة جزئياً",
  paid: "مسددة بالكامل",
  rejected: "مرفوضة",
  cancelled: "ملغاة",
  closed: "مغلقة",
  converted: "محوّلة",
  active: "نشط",
  inactive: "معطل",
  pending: "قيد الانتظار",
  in_transit: "قيد النقل",
  completed: "مكتمل",
};

export function formatStatus(status: string | null | undefined): string {
  if (!status) return "—";
  return STATUS_ARABIC[status.toLowerCase()] ?? titleCase(status);
}

type StatusTone = "slate" | "amber" | "blue" | "emerald" | "red" | "violet";

const STATUS_TONE: Record<string, StatusTone> = {
  draft: "slate",
  submitted: "amber",
  approved: "blue",
  partially_received: "amber",
  received: "emerald",
  posted: "emerald",
  partially_paid: "amber",
  paid: "emerald",
  rejected: "red",
  cancelled: "red",
  closed: "slate",
  converted: "violet",
  active: "emerald",
  inactive: "slate",
};

export function statusTone(status: string): StatusTone {
  return STATUS_TONE[status] ?? "slate";
}
