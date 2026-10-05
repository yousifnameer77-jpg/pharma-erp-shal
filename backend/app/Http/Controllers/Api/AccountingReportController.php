<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Services\AgingReportService;
use App\Services\FinancialStatementService;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only accounting reports, every one of them computed on demand from
 * journal_entry_lines / invoices — see GeneralLedgerService,
 * FinancialStatementService and AgingReportService for how each figure is
 * derived. Nothing here is cached or stored.
 */
class AccountingReportController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $generalLedgerService,
        private readonly FinancialStatementService $financialStatementService,
        private readonly AgingReportService $agingReportService,
    ) {
    }

    private function resolveCompanyId(Request $request): string
    {
        return $request->query('company_id')
            ?? $request->user()?->branch?->company_id
            ?? \App\Models\Company::value('id')
            ?? '';
    }

    /** Trial balance: every account's net balance as of a date. */
    public function generalLedger(Request $request): JsonResponse
    {
        $rows = $this->generalLedgerService->trialBalance(
            $this->resolveCompanyId($request),
            $request->query('as_of_date'),
        );

        return response()->json(['data' => $rows]);
    }

    /** One account's posting history with a running balance. */
    public function accountLedger(Request $request, ChartOfAccount $chartOfAccount): JsonResponse
    {
        $ledger = $this->generalLedgerService->ledgerForAccount(
            $chartOfAccount,
            $request->query('date_from'),
            $request->query('date_to'),
        );

        return response()->json(['data' => $ledger]);
    }

    /** Every `category = cash` account with its current balance. */
    public function cash(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->balancesForCategory(
            $this->resolveCompanyId($request),
            ChartOfAccount::CATEGORY_CASH,
            $request->query('as_of_date'),
        )]);
    }

    /** Every `category = bank` account with its current balance (a company can have several). */
    public function banks(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->balancesForCategory(
            $this->resolveCompanyId($request),
            ChartOfAccount::CATEGORY_BANK,
            $request->query('as_of_date'),
        )]);
    }

    /** Accounts Receivable aging, bucketed by days overdue and grouped by customer. */
    public function receivables(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->agingReportService->receivablesAging(
            $this->resolveCompanyId($request),
            $request->query('as_of_date'),
        )]);
    }

    /** Accounts Payable aging, bucketed by days overdue and grouped by supplier. */
    public function payables(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->agingReportService->payablesAging(
            $this->resolveCompanyId($request),
            $request->query('as_of_date'),
        )]);
    }

    public function profitAndLoss(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->financialStatementService->profitAndLoss(
            $this->resolveCompanyId($request),
            $request->query('date_from'),
            $request->query('date_to'),
        )]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->financialStatementService->balanceSheet(
            $this->resolveCompanyId($request),
            $request->query('as_of_date'),
        )]);
    }

    private function balancesForCategory(string $companyId, string $category, ?string $asOfDate): array
    {
        return collect($this->generalLedgerService->trialBalance($companyId, $asOfDate))
            ->filter(fn (array $row) => $row['account']->category === $category)
            ->values()
            ->all();
    }
}
