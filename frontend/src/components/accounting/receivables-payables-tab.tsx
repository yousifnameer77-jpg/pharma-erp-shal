"use client";

import { useState } from "react";
import { useApiResource } from "@/lib/hooks";
import { formatMoney, todayIso } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { DataTable, type Column } from "@/components/ui/table";
import { Input } from "@/components/ui/field";
import { Tabs } from "@/components/ui/tabs";
import type {
  AgingBuckets,
  PayablesAging,
  ReceivablesAging,
} from "@/lib/types";

function bucketColumns<T extends { buckets: AgingBuckets; total: number }>(
  nameOf: (row: T) => string,
): Column<T>[] {
  return [
    {
      key: "party",
      header: "Party",
      render: (r) => (
        <span className="font-medium text-slate-900">{nameOf(r)}</span>
      ),
    },
    {
      key: "current",
      header: "Current",
      render: (r) => formatMoney(r.buckets?.current ?? 0),
      className: "text-right",
    },
    {
      key: "1_30",
      header: "1–30 days",
      render: (r) => formatMoney(r.buckets?.["1_30"] ?? 0),
      className: "text-right",
    },
    {
      key: "31_60",
      header: "31–60 days",
      render: (r) => formatMoney(r.buckets?.["31_60"] ?? 0),
      className: "text-right",
    },
    {
      key: "61_90",
      header: "61–90 days",
      render: (r) => formatMoney(r.buckets?.["61_90"] ?? 0),
      className: "text-right",
    },
    {
      key: "90_plus",
      header: "90+ days",
      render: (r) => formatMoney(r.buckets?.["90_plus"] ?? 0),
      className: "text-right",
    },
    {
      key: "total",
      header: "Total due",
      render: (r) => (
        <span className="font-semibold text-slate-900">
          {formatMoney(r.total ?? 0)}
        </span>
      ),
      className: "text-right",
    },
  ];
}

export function ReceivablesPayablesTab({ companyId }: { companyId: string }) {
  const [asOfDate, setAsOfDate] = useState(todayIso());
  const [active, setActive] = useState("receivables");

  const receivables = useApiResource<ReceivablesAging>(
    "/v1/accounting/receivables",
    {
      company_id: companyId,
      as_of_date: asOfDate,
    },
  );
  const payables = useApiResource<PayablesAging>("/v1/accounting/payables", {
    company_id: companyId,
    as_of_date: asOfDate,
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-3">
        <Tabs
          tabs={[
            { key: "receivables", label: "Receivables" },
            { key: "payables", label: "Payables" },
          ]}
          active={active}
          onChange={setActive}
        />
        <Input
          label="As of date"
          type="date"
          value={asOfDate}
          onChange={(e) => setAsOfDate(e.target.value)}
          wrapClassName="w-44"
        />
      </div>

      {active === "receivables" && (
        <Card>
          <CardHeader
            title="Accounts receivable aging"
            description="What customers owe, bucketed by days overdue."
            action={
              receivables.data && (
                <span className="text-sm font-semibold text-slate-900">
                  Grand total {formatMoney(receivables.data.grand_total)}
                </span>
              )
            }
          />
          <DataTable
            columns={bucketColumns<ReceivablesAging["customers"][number]>(
              (r) => r.customer?.name ?? "—",
            )}
            rows={receivables.data?.customers ?? []}
            rowKey={(r) => r.customer?.id ?? String(Math.random())}
            loading={receivables.loading}
            error={receivables.error}
            emptyTitle="No outstanding receivables"
          />
        </Card>
      )}

      {active === "payables" && (
        <Card>
          <CardHeader
            title="Accounts payable aging"
            description="What's owed to suppliers, bucketed by days overdue."
            action={
              payables.data && (
                <span className="text-sm font-semibold text-slate-900">
                  Grand total {formatMoney(payables.data.grand_total)}
                </span>
              )
            }
          />
          <DataTable
            columns={bucketColumns<PayablesAging["suppliers"][number]>(
              (r) => r.supplier?.name ?? "—",
            )}
            rows={payables.data?.suppliers ?? []}
            rowKey={(r) => r.supplier?.id ?? String(Math.random())}
            loading={payables.loading}
            error={payables.error}
            emptyTitle="No outstanding payables"
          />
        </Card>
      )}
    </div>
  );
}
