"use client";

import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useApiResource } from "@/lib/hooks";
import { todayIso } from "@/lib/format";
import { PERMISSIONS } from "@/lib/permissions";
import { useAuth } from "@/lib/auth-context";
import { Card, CardHeader } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/field";
import { DataTable, type Column } from "@/components/ui/table";
import { StatCard } from "@/components/ui/stat-card";
import { useToast } from "@/components/ui/toast";
import { Banknote } from "lucide-react";

interface ExchangeRate {
  id: string;
  currency: string;
  rate_date: string;
  rate: string | number;
}

const columns: Column<ExchangeRate>[] = [
  {
    key: "date",
    header: "التاريخ",
    render: (r) => <span className="font-mono text-xs">{String(r.rate_date).slice(0, 10)}</span>,
  },
  { key: "currency", header: "العملة", render: (r) => r.currency },
  {
    key: "rate",
    header: "سعر الصرف (د.ع لكل 1 دولار)",
    render: (r) => (
      <span className="font-medium text-slate-900">
        {Number(r.rate).toLocaleString(undefined, { maximumFractionDigits: 4 })}
      </span>
    ),
    className: "text-right",
  },
];

/**
 * Manual daily USD -> IQD rate. Dollar invoices and payments pick up the
 * rate in force on their date; one rate per day (saving again corrects it).
 */
export function ExchangeRatesTab({ companyId }: { companyId: string }) {
  const toast = useToast();
  const { hasPermission } = useAuth();
  const canEdit = hasPermission(PERMISSIONS.financeManage);

  const rates = useApiResource<ExchangeRate[]>("/v1/exchange-rates", { company_id: companyId });
  const [date, setDate] = useState(todayIso());
  const [rate, setRate] = useState("");
  const [busy, setBusy] = useState(false);

  const latest = rates.data?.[0];

  async function save(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await api.post("/v1/exchange-rates", {
        company_id: companyId,
        currency: "USD",
        rate_date: date,
        rate: Number(rate),
      });
      toast.success("تم حفظ سعر الصرف.");
      setRate("");
      rates.refetch();
    } catch (err) {
      toast.error(err instanceof ApiError ? err.summary : "تعذر حفظ سعر الصرف.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <StatCard
        label={latest ? `آخر سعر (${String(latest.rate_date).slice(0, 10)})` : "لم يُدخل أي سعر بعد"}
        value={latest ? Number(latest.rate).toLocaleString() : "—"}
        icon={Banknote}
      />

      {canEdit && (
        <Card>
          <CardHeader title="إدخال سعر اليوم" description="الدينار العراقي هو العملة الأساسية. أدخل سعر الدولار يدوياً مرة كل يوم قبل إصدار أي فاتورة بالدولار." />
          <form onSubmit={save} className="flex flex-wrap items-end gap-3 p-4">
            <Input label="التاريخ" type="date" max={todayIso()} value={date} onChange={(e) => setDate(e.target.value)} required />
            <Input label="د.ع لكل 1 دولار" type="number" step="0.0001" min="0" value={rate} onChange={(e) => setRate(e.target.value)} required />
            <Button type="submit" loading={busy}>حفظ</Button>
          </form>
        </Card>
      )}

      <Card>
        <CardHeader title="سجل الأسعار" />
        <DataTable
          columns={columns}
          rows={rates.data ?? []}
          rowKey={(r) => r.id}
          loading={rates.loading}
          error={rates.error}
          onRetry={rates.refetch}
        />
      </Card>
    </div>
  );
}
