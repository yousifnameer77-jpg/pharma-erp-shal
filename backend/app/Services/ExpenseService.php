<?php

namespace App\Services;

use App\Exceptions\Accounting\InvalidStatusTransitionException;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Expenses" — the fourth thing the Accounting module auto-posts (alongside
 * Sales, Purchases and Payments). An expense is paid immediately from a
 * named cash/bank account (see the `expenses` migration for why accrued/
 * unpaid expenses aren't modeled); post() is what creates the journal entry
 * — Dr every line's expense account, Cr the paid-from account — and,
 * because it represents money already spent, is irreversible from there
 * (only a draft can still be edited or cancelled).
 */
class ExpenseService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    /**
     * @param  array{company_id: string, branch_id?: ?string, expense_date: string, paid_from_account_id: string, notes?: ?string, items: array<int, array{account_id: string, amount: float, description?: ?string}>}  $data
     */
    public function create(array $data, User $user): Expense
    {
        return DB::transaction(function () use ($data, $user) {
            $expense = Expense::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'expense_number' => $this->sequences->next($data['company_id'], 'expense', 'EXP'),
                'expense_date' => $data['expense_date'],
                'paid_from_account_id' => $data['paid_from_account_id'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->syncItems($expense, $data['items']);

            return $expense->fresh('items');
        });
    }

    /**
     * @param  array{branch_id?: ?string, paid_from_account_id?: ?string, notes?: ?string, items?: array<int, array{account_id: string, amount: float, description?: ?string}>}  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        $this->guard($expense, 'draft', 'update');

        return DB::transaction(function () use ($expense, $data) {
            $expense->update([
                'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $expense->branch_id,
                'paid_from_account_id' => $data['paid_from_account_id'] ?? $expense->paid_from_account_id,
                'notes' => $data['notes'] ?? $expense->notes,
            ]);

            if (array_key_exists('items', $data)) {
                $expense->items()->delete();
                $this->syncItems($expense, $data['items']);
            }

            return $expense->fresh('items');
        });
    }

    public function post(Expense $expense, User $user): Expense
    {
        $this->guard($expense, 'draft', 'posted');

        return DB::transaction(function () use ($expense, $user) {
            $expense = Expense::with('items')->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            $lines = $expense->items->map(fn ($item) => [
                'account_id' => $item->account_id,
                'debit' => (float) $item->amount,
                'description' => $item->description,
            ])->all();

            $lines[] = [
                'account_id' => $expense->paid_from_account_id,
                'credit' => (float) $expense->total_amount,
                'description' => 'Expense paid',
            ];

            $this->journalEntryService->post(
                companyId: $expense->company_id,
                branchId: $expense->branch_id,
                entryDate: $expense->expense_date->toDateString(),
                description: "Expense {$expense->expense_number}",
                lines: $lines,
                user: $user,
                referenceType: 'expense',
                referenceId: $expense->id,
            );

            $expense->update(['status' => 'posted', 'posted_at' => now()]);

            return $expense->fresh('items');
        });
    }

    public function cancel(Expense $expense): Expense
    {
        $this->guard($expense, 'draft', 'cancelled');
        $expense->update(['status' => 'cancelled']);

        return $expense->fresh();
    }

    /**
     * @param  array<int, array{account_id: string, amount: float, description?: ?string}>  $items
     */
    private function syncItems(Expense $expense, array $items): void
    {
        $total = 0.0;

        foreach ($items as $item) {
            $amount = (float) $item['amount'];

            $expense->items()->create([
                'account_id' => $item['account_id'],
                'amount' => $amount,
                'description' => $item['description'] ?? null,
            ]);

            $total += $amount;
        }

        $expense->update(['total_amount' => $total]);
    }

    private function guard(Expense $expense, string $expected, string $to): void
    {
        if ($expense->status !== $expected) {
            throw new InvalidStatusTransitionException('expense', $expense->id, $expense->status, $to);
        }
    }
}
