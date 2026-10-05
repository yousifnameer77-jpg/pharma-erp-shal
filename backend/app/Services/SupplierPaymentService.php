<?php

namespace App\Services;

use App\Exceptions\Purchasing\InvalidStatusTransitionException;
use App\Exceptions\Purchasing\OverpaymentException;
use App\Models\ChartOfAccount;
use App\Models\PurchaseInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Supplier Balance" step of the workflow: recording a payment is what
 * reduces it. A payment posts atomically — its invoice allocations and its
 * journal entry (Dr Accounts Payable, Cr the chosen cash/bank account) are
 * created in the same transaction as the payment row itself, and
 * SupplierPayment is append-only afterward (see SupplierPaymentImmutableException).
 */
class SupplierPaymentService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    /**
     * @param  array{company_id: string, supplier_id: string, payment_date: string, amount: float, method: string, paid_from_account_id: string, reference?: ?string, notes?: ?string, allocations?: array<int, array{purchase_invoice_id: string, amount: float}>}  $data
     */
    public function record(array $data, User $user): SupplierPayment
    {
        $amount = (float) $data['amount'];

        return DB::transaction(function () use ($data, $amount, $user) {
            $allocations = $data['allocations'] ?? $this->autoAllocate($data['company_id'], $data['supplier_id'], $amount);

            $allocatedTotal = round(array_sum(array_map(fn ($a) => (float) $a['amount'], $allocations)), 3);
            if (abs($allocatedTotal - round($amount, 3)) > 0.001) {
                throw ValidationException::withMessages([
                    'allocations' => ["Allocations must sum to the payment amount ({$amount}); they sum to {$allocatedTotal}."],
                ]);
            }

            $payment = SupplierPayment::create([
                'company_id' => $data['company_id'],
                'supplier_id' => $data['supplier_id'],
                'payment_number' => $this->sequences->next($data['company_id'], 'supplier_payment', 'PAY'),
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'method' => $data['method'],
                'paid_from_account_id' => $data['paid_from_account_id'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($allocations as $allocation) {
                $invoice = PurchaseInvoice::whereKey($allocation['purchase_invoice_id'])->lockForUpdate()->firstOrFail();

                if (! in_array($invoice->status, ['posted', 'partially_paid'], true)) {
                    throw new InvalidStatusTransitionException('purchase_invoice', $invoice->id, $invoice->status, 'paid');
                }

                $allocatedAmount = (float) $allocation['amount'];
                $remainingDue = (float) $invoice->total_amount - (float) $invoice->paid_amount;

                if ($allocatedAmount > $remainingDue + 0.001) {
                    throw new OverpaymentException($invoice->id, $remainingDue, $allocatedAmount);
                }

                $payment->allocations()->create([
                    'purchase_invoice_id' => $invoice->id,
                    'amount' => $allocatedAmount,
                ]);

                $newPaidAmount = (float) $invoice->paid_amount + $allocatedAmount;
                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $newPaidAmount >= (float) $invoice->total_amount - 0.001 ? 'paid' : 'partially_paid',
                ]);
            }

            $payableAccount = ChartOfAccount::findByCode($data['company_id'], ChartOfAccount::CODE_ACCOUNTS_PAYABLE);

            $this->journalEntryService->post(
                companyId: $data['company_id'],
                branchId: null,
                entryDate: $data['payment_date'],
                description: "Supplier payment {$payment->payment_number}",
                lines: [
                    ['account_id' => $payableAccount->id, 'debit' => $amount, 'description' => 'Accounts payable settled'],
                    ['account_id' => $data['paid_from_account_id'], 'credit' => $amount, 'description' => 'Paid out'],
                ],
                user: $user,
                referenceType: 'supplier_payment',
                referenceId: $payment->id,
            );

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * No allocations given: apply the payment across the supplier's oldest
     * outstanding invoices first (FIFO by invoice_date) — the payment
     * equivalent of Inventory's FEFO sale allocation.
     *
     * @return array<int, array{purchase_invoice_id: string, amount: float}>
     */
    private function autoAllocate(string $companyId, string $supplierId, float $amount): array
    {
        $invoices = PurchaseInvoice::query()
            ->where('company_id', $companyId)
            ->where('supplier_id', $supplierId)
            ->whereIn('status', ['posted', 'partially_paid'])
            ->orderBy('invoice_date')
            ->lockForUpdate()
            ->get();

        $remaining = $amount;
        $allocations = [];

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $due = (float) $invoice->total_amount - (float) $invoice->paid_amount;
            if ($due <= 0) {
                continue;
            }

            $take = min($due, $remaining);
            $allocations[] = ['purchase_invoice_id' => $invoice->id, 'amount' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0.001) {
            throw ValidationException::withMessages([
                'amount' => ["Payment of {$amount} exceeds this supplier's total outstanding balance; only ".($amount - $remaining).' could be allocated. Pass explicit `allocations` to overpay a specific invoice, or reduce the amount.'],
            ]);
        }

        return $allocations;
    }
}
