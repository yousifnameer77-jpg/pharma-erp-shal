<?php

namespace App\Exceptions\Accounting;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Raised by JournalEntryService::post() before anything is written: the sum
 * of every line's debit must equal the sum of every line's credit, or the
 * ledger stops being self-consistent. Never caused by user input alone —
 * always a bug in the posting module (Purchasing, Sales, ...) that built the
 * line array, so this is a 500, not a 422.
 */
class UnbalancedJournalEntryException extends Exception
{
    public function __construct(public readonly float $totalDebit, public readonly float $totalCredit)
    {
        parent::__construct("Journal entry is not balanced: total debit {$totalDebit} does not equal total credit {$totalCredit}.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'unbalanced_journal_entry',
            'total_debit' => $this->totalDebit,
            'total_credit' => $this->totalCredit,
        ], 500);
    }
}
