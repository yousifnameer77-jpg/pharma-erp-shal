<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/**
 * The "General Ledger": trialBalance() is the summary view (every account's
 * net balance as of a date — the classic trial balance), ledgerForAccount()
 * is the drill-down (one account's full posting history with a running
 * balance). Both read straight from journal_entry_lines/journal_entries —
 * there's nothing else to keep in sync, since JournalEntryService is the
 * only thing that ever writes there.
 */
class GeneralLedgerService
{
    private const DEBIT_NORMAL = ['asset', 'expense'];

    /**
     * @return array<int, array{account: ChartOfAccount, total_debit: float, total_credit: float, balance: float}>
     */
    public function trialBalance(string $companyId, ?string $asOfDate = null): array
    {
        $rows = $this->lineTotalsQuery($companyId, null, $asOfDate)
            ->groupBy('journal_entry_lines.account_id')
            ->get(['journal_entry_lines.account_id', DB::raw('COALESCE(SUM(debit), 0) AS total_debit'), DB::raw('COALESCE(SUM(credit), 0) AS total_credit')])
            ->keyBy('account_id');

        return ChartOfAccount::where('company_id', $companyId)
            ->orderBy('code')
            ->get()
            ->map(function (ChartOfAccount $account) use ($rows) {
                $row = $rows->get($account->id);
                $totalDebit = (float) ($row->total_debit ?? 0);
                $totalCredit = (float) ($row->total_credit ?? 0);
                $balance = in_array($account->type, self::DEBIT_NORMAL, true)
                    ? $totalDebit - $totalCredit
                    : $totalCredit - $totalDebit;

                return [
                    'account' => $account,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'balance' => $balance,
                ];
            })
            ->all();
    }

    /**
     * @return array{account: ChartOfAccount, opening_balance: float, closing_balance: float, transactions: array<int, array{date: string, entry_number: string, description: ?string, debit: float, credit: float, running_balance: float}>}
     */
    public function ledgerForAccount(ChartOfAccount $account, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $debitNormal = in_array($account->type, self::DEBIT_NORMAL, true);

        $openingRow = $this->lineTotalsQuery($account->company_id, $account->id, $dateFrom ? null : null)
            ->when($dateFrom, fn ($q) => $q->where('journal_entries.entry_date', '<', $dateFrom))
            ->when(! $dateFrom, fn ($q) => $q->whereRaw('1 = 0')) // no dateFrom -> nothing precedes it, opening balance is 0
            ->first(['journal_entry_lines.account_id', DB::raw('COALESCE(SUM(debit), 0) AS total_debit'), DB::raw('COALESCE(SUM(credit), 0) AS total_credit')]);

        $openingDebit = (float) ($openingRow->total_debit ?? 0);
        $openingCredit = (float) ($openingRow->total_credit ?? 0);
        $runningBalance = $debitNormal ? $openingDebit - $openingCredit : $openingCredit - $openingDebit;
        $openingBalance = $runningBalance;

        $lines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->when($dateFrom, fn ($q) => $q->where('journal_entries.entry_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('journal_entries.entry_date', '<=', $dateTo))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.entry_number')
            ->get([
                'journal_entries.entry_date', 'journal_entries.entry_number', 'journal_entry_lines.description',
                'journal_entry_lines.debit', 'journal_entry_lines.credit',
            ]);

        $transactions = $lines->map(function ($line) use (&$runningBalance, $debitNormal) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $runningBalance += $debitNormal ? $debit - $credit : $credit - $debit;

            return [
                'date' => $line->entry_date,
                'entry_number' => $line->entry_number,
                'description' => $line->description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance,
            ];
        })->all();

        return [
            'account' => $account,
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'transactions' => $transactions,
        ];
    }

    private function lineTotalsQuery(string $companyId, ?string $accountId, ?string $asOfDate)
    {
        return DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->when($accountId, fn ($q) => $q->where('journal_entry_lines.account_id', $accountId))
            ->when($asOfDate, fn ($q) => $q->where('journal_entries.entry_date', '<=', $asOfDate));
    }
}
