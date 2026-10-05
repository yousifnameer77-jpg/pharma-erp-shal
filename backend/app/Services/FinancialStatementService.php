<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/**
 * Profit & Loss and Balance Sheet, both computed on demand straight from
 * journal_entry_lines/journal_entries — same "never store a balance"
 * philosophy as GeneralLedgerService.
 *
 * Simplification (documented in README): the system has no period-closing
 * entries, so the Balance Sheet has no "Retained Earnings" account to read.
 * Equity is balanced with a synthetic "Current Earnings" line = all-time
 * revenue minus all-time expense as of the report date. This keeps the
 * sheet balanced (Assets = Liabilities + Equity) without ever needing a
 * closing/rollover process, at the cost of not distinguishing prior-year
 * retained earnings from the current period's — a real close would replace
 * this line with a permanent Retained Earnings balance plus a period P&L.
 */
class FinancialStatementService
{
    /**
     * @return array{
     *     date_from: ?string, date_to: ?string,
     *     revenue: array<int, array{account: ChartOfAccount, amount: float}>, total_revenue: float,
     *     expenses: array<int, array{account: ChartOfAccount, amount: float}>, total_expense: float,
     *     net_profit: float,
     * }
     */
    public function profitAndLoss(string $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $rows = $this->lineTotalsQuery($companyId, $dateFrom, $dateTo)
            ->whereIn('chart_of_accounts.type', ['revenue', 'expense'])
            ->groupBy('journal_entry_lines.account_id')
            ->get(['journal_entry_lines.account_id', DB::raw('COALESCE(SUM(debit), 0) AS total_debit'), DB::raw('COALESCE(SUM(credit), 0) AS total_credit')])
            ->keyBy('account_id');

        $accounts = ChartOfAccount::where('company_id', $companyId)
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get();

        $revenue = [];
        $expenses = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($accounts as $account) {
            $row = $rows->get($account->id);
            $totalDebit = (float) ($row->total_debit ?? 0);
            $totalCredit = (float) ($row->total_credit ?? 0);

            if ($account->type === 'revenue') {
                // Credit-normal: sales/returns net to a credit balance.
                $amount = $totalCredit - $totalDebit;
                if ($amount == 0.0) {
                    continue;
                }
                $revenue[] = ['account' => $account, 'amount' => $amount];
                $totalRevenue += $amount;
            } else {
                // Debit-normal.
                $amount = $totalDebit - $totalCredit;
                if ($amount == 0.0) {
                    continue;
                }
                $expenses[] = ['account' => $account, 'amount' => $amount];
                $totalExpense += $amount;
            }
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'revenue' => $revenue,
            'total_revenue' => $totalRevenue,
            'expenses' => $expenses,
            'total_expense' => $totalExpense,
            'net_profit' => $totalRevenue - $totalExpense,
        ];
    }

    /**
     * @return array{
     *     as_of_date: ?string,
     *     assets: array<int, array{account: ChartOfAccount, amount: float}>, total_assets: float,
     *     liabilities: array<int, array{account: ChartOfAccount, amount: float}>, total_liabilities: float,
     *     equity: array<int, array{account: ChartOfAccount, amount: float}>, current_earnings: float, total_equity: float,
     *     is_balanced: bool,
     * }
     */
    public function balanceSheet(string $companyId, ?string $asOfDate = null): array
    {
        $rows = $this->lineTotalsQuery($companyId, null, $asOfDate)
            ->whereIn('chart_of_accounts.type', ['asset', 'liability', 'equity'])
            ->groupBy('journal_entry_lines.account_id')
            ->get(['journal_entry_lines.account_id', DB::raw('COALESCE(SUM(debit), 0) AS total_debit'), DB::raw('COALESCE(SUM(credit), 0) AS total_credit')])
            ->keyBy('account_id');

        $accounts = ChartOfAccount::where('company_id', $companyId)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get();

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        foreach ($accounts as $account) {
            $row = $rows->get($account->id);
            $totalDebit = (float) ($row->total_debit ?? 0);
            $totalCredit = (float) ($row->total_credit ?? 0);

            if ($account->type === 'asset') {
                $amount = $totalDebit - $totalCredit;
                if ($amount == 0.0) {
                    continue;
                }
                $assets[] = ['account' => $account, 'amount' => $amount];
                $totalAssets += $amount;
            } elseif ($account->type === 'liability') {
                $amount = $totalCredit - $totalDebit;
                if ($amount == 0.0) {
                    continue;
                }
                $liabilities[] = ['account' => $account, 'amount' => $amount];
                $totalLiabilities += $amount;
            } else {
                $amount = $totalCredit - $totalDebit;
                if ($amount == 0.0) {
                    continue;
                }
                $equity[] = ['account' => $account, 'amount' => $amount];
                $totalEquity += $amount;
            }
        }

        // Synthetic plug — see class docblock.
        $currentEarnings = $this->profitAndLoss($companyId, null, $asOfDate)['net_profit'];
        $totalEquity += $currentEarnings;

        return [
            'as_of_date' => $asOfDate,
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equity,
            'current_earnings' => $currentEarnings,
            'total_equity' => $totalEquity,
            'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.001,
        ];
    }

    private function lineTotalsQuery(string $companyId, ?string $dateFrom, ?string $dateTo)
    {
        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->when($dateFrom, fn ($q) => $q->where('journal_entries.entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('journal_entries.entry_date', '<=', $dateTo));
    }
}
