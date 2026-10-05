<?php

namespace App\Services;

use App\Exceptions\Accounting\UnbalancedJournalEntryException;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place journal_entries/journal_entry_lines rows are created.
 * Every posting action in the app (goods receipt, purchase invoice, supplier
 * payment, and later Sales/Finance) builds a $lines array and calls post() —
 * nothing writes to the ledger directly, the same discipline
 * StockMovementService enforces for stock.
 */
class JournalEntryService
{
    public function __construct(private readonly DocumentSequenceService $sequences)
    {
    }

    /**
     * @param  array<int, array{account_id: string, debit?: float, credit?: float, description?: ?string}>  $lines
     */
    public function post(
        string $companyId,
        ?string $branchId,
        string $entryDate,
        string $description,
        array $lines,
        User $user,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): JournalEntry {
        $totalDebit = round(array_sum(array_map(fn ($line) => (float) ($line['debit'] ?? 0), $lines)), 3);
        $totalCredit = round(array_sum(array_map(fn ($line) => (float) ($line['credit'] ?? 0), $lines)), 3);

        if ($totalDebit !== $totalCredit) {
            throw new UnbalancedJournalEntryException($totalDebit, $totalCredit);
        }

        return DB::transaction(function () use ($companyId, $branchId, $entryDate, $description, $lines, $user, $referenceType, $referenceId) {
            $entry = JournalEntry::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'entry_number' => $this->sequences->next($companyId, 'journal_entry', 'JE'),
                'entry_date' => $entryDate,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }
}
