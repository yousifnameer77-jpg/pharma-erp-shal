"use client";

import { useState } from "react";
import { PageHeader } from "@/components/ui/page-header";
import { Tabs } from "@/components/ui/tabs";
import { PermissionGate } from "@/components/layout/permission-gate";
import { PERMISSIONS } from "@/lib/permissions";
import { useAuth } from "@/lib/auth-context";
import { ChartOfAccountsTab } from "@/components/accounting/chart-of-accounts-tab";
import { JournalEntriesTab } from "@/components/accounting/journal-entries-tab";
import { ExpensesTab } from "@/components/accounting/expenses-tab";
import { GeneralLedgerTab } from "@/components/accounting/general-ledger-tab";
import { CashBanksTab } from "@/components/accounting/cash-banks-tab";
import { ReceivablesPayablesTab } from "@/components/accounting/receivables-payables-tab";
import { ProfitLossTab } from "@/components/accounting/profit-loss-tab";
import { ExchangeRatesTab } from "@/components/accounting/exchange-rates-tab";
import { BalanceSheetTab } from "@/components/accounting/balance-sheet-tab";

const TABS = [
  { key: "chart", label: "شجرة الحسابات" },
  { key: "journal", label: "قيود اليومية" },
  { key: "expenses", label: "المصروفات النثرية" },
  { key: "ledger", label: "دفتر الأستاذ العام" },
  { key: "cash", label: "الصناديق والبنوك" },
  { key: "aging", label: "أعمار الذمم والديون" },
  { key: "pnl", label: "الأرباح والخسائر" },
  { key: "balance", label: "الميزانية العمومية" },
  { key: "fx", label: "أسعار الصرف (USD)" },
];

function AccountingContent({ companyId }: { companyId: string }) {
  const [active, setActive] = useState("chart");

  return (
    <div className="flex flex-col gap-4">
      <PageHeader
        title="النظام المحاسبي والمالي"
        description="شجرة الحسابات، القيود اليومية، المصروفات، دفتر الأستاذ، والقوائم الختامية — ترحيل تلقائي من فواتير البيع والشراء والمدفوعات والمصروفات."
      />
      <Tabs tabs={TABS} active={active} onChange={setActive} />
      {active === "chart" && <ChartOfAccountsTab companyId={companyId} />}
      {active === "journal" && <JournalEntriesTab companyId={companyId} />}
      {active === "expenses" && <ExpensesTab companyId={companyId} />}
      {active === "ledger" && <GeneralLedgerTab companyId={companyId} />}
      {active === "cash" && <CashBanksTab companyId={companyId} />}
      {active === "aging" && <ReceivablesPayablesTab companyId={companyId} />}
      {active === "pnl" && <ProfitLossTab companyId={companyId} />}
      {active === "balance" && <BalanceSheetTab companyId={companyId} />}
      {active === "fx" && <ExchangeRatesTab companyId={companyId} />}
    </div>
  );
}

export default function AccountingPage() {
  const { user } = useAuth();
  const companyId =
    user?.company_id ??
    (user as any)?.branch?.company_id ??
    "a2cb2e70-bdda-4b32-a0e9-b095428de4c5";

  return (
    <PermissionGate permission={PERMISSIONS.financeJournalView}>
      {companyId ? (
        <AccountingContent companyId={companyId} />
      ) : null}
    </PermissionGate>
  );
}
