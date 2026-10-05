<?php

namespace App\Services;

use App\Exceptions\Sales\InvalidStatusTransitionException;
use App\Exceptions\Sales\OverpaymentException;
use App\Models\ChartOfAccount;
use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Customer Balance" step: recording a payment (a receipt) is what reduces
 * it. Exact mirror of SupplierPaymentService, direction flipped — see that
 * class's docblock for the general pattern (FIFO auto-allocation, atomic
 * posting, append-only afterward).
 */
class CustomerPaymentService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly JournalEntryService $journalEntryService,
        private readonly ExchangeRateService $fx,
    ) {
    }

    /**
     * @param  array{company_id: string, customer_id: string, payment_date: string, amount: float, method: string, received_into_account_id: string, reference?: ?string, notes?: ?string, allocations?: array<int, array{sales_invoice_id: string, amount: float}>}  $data
     */
    public function record(array $data, User $user): CustomerPayment
    {
        $amount = (float) $data['amount'];
        $currency = $data['currency'] ?? ExchangeRateService::BASE;
        $rate = $this->fx->rateFor($data['company_id'], $currency, $data['payment_date']);

        return DB::transaction(function () use ($data, $amount, $user, $currency, $rate) {
            $allocations = $data['allocations'] ?? $this->autoAllocate($data['company_id'], $data['customer_id'], $amount, $currency);

            $allocatedTotal = round(array_sum(array_map(fn ($a) => (float) $a['amount'], $allocations)), 3);
            if (abs($allocatedTotal - round($amount, 3)) > 0.001) {
                throw ValidationException::withMessages([
                    'allocations' => ["Allocations must sum to the payment amount ({$amount}); they sum to {$allocatedTotal}."],
                ]);
            }

            $payment = CustomerPayment::create([
                'company_id' => $data['company_id'],
                'customer_id' => $data['customer_id'],
                'currency' => $currency,
                'exchange_rate' => $rate,
                'payment_number' => $this->sequences->next($data['company_id'], 'customer_payment', 'RCT'),
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'method' => $data['method'],
                'received_into_account_id' => $data['received_into_account_id'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $receivableBase = 0.0;

            foreach ($allocations as $allocation) {
                $invoice = SalesInvoice::whereKey($allocation['sales_invoice_id'])->lockForUpdate()->firstOrFail();

                if ($invoice->currency !== $currency) {
                    throw ValidationException::withMessages([
                        'currency' => ["Invoice {$invoice->invoice_number} is in {$invoice->currency}; a {$currency} payment cannot be applied to it."],
                    ]);
                }

                if (! in_array($invoice->status, ['posted', 'partially_paid'], true)) {
                    throw new InvalidStatusTransitionException('sales_invoice', $invoice->id, $invoice->status, 'paid');
                }

                $allocatedAmount = (float) $allocation['amount'];
                $remainingDue = (float) $invoice->total_amount - (float) $invoice->paid_amount;

                if ($allocatedAmount > $remainingDue + 0.001) {
                    throw new OverpaymentException($invoice->id, $remainingDue, $allocatedAmount);
                }

                $payment->allocations()->create([
                    'sales_invoice_id' => $invoice->id,
                    'amount' => $allocatedAmount,
                ]);

                // Receivable is cleared at the rate the invoice was booked at.
                $receivableBase += $this->fx->toBase($allocatedAmount, (float) $invoice->exchange_rate);

                $newPaidAmount = (float) $invoice->paid_amount + $allocatedAmount;
                $invoice->update([
                    'paid_amount' => $newPaidAmount,
                    'status' => $newPaidAmount >= (float) $invoice->total_amount - 0.001 ? 'paid' : 'partially_paid',
                ]);
            }

            $receivableAccount = ChartOfAccount::findByCode($data['company_id'], ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);
            $receivedBase = $this->fx->toBase($amount, $rate);
            $difference = round($receivedBase - $receivableBase, 3);

            $lines = [
                ['account_id' => $data['received_into_account_id'], 'debit' => $receivedBase, 'description' => 'Received'],
                ['account_id' => $receivableAccount->id, 'credit' => $receivableBase, 'description' => 'Accounts receivable settled'],
            ];

            // Collected more IQD than the receivable carried = gain; less = loss.
            if ($difference !== 0.0) {
                $fxAccount = $this->fx->gainLossAccount($data['company_id']);
                $lines[] = $difference > 0
                    ? ['account_id' => $fxAccount->id, 'credit' => $difference, 'description' => 'Realized exchange gain']
                    : ['account_id' => $fxAccount->id, 'debit' => -$difference, 'description' => 'Realized exchange loss'];
            }

            $this->journalEntryService->post(
                companyId: $data['company_id'],
                branchId: null,
                entryDate: $data['payment_date'],
                description: "Customer payment {$payment->payment_number}",
                lines: $lines,
                user: $user,
                referenceType: 'customer_payment',
                referenceId: $payment->id,
            );

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * No allocations given: apply the payment across the customer's oldest
     * outstanding invoices first (FIFO by invoice_date) — mirrors
     * SupplierPaymentService::autoAllocate() and Inventory's FEFO sale
     * allocation.
     *
     * @return array<int, array{sales_invoice_id: string, amount: float}>
     */
    private function autoAllocate(string $companyId, string $customerId, float $amount, string $currency): array
    {
        $invoices = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('currency', $currency)
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
            $allocations[] = ['sales_invoice_id' => $invoice->id, 'amount' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0.001) {
            throw ValidationException::withMessages([
                'amount' => ["Payment of {$amount} exceeds this customer's total outstanding balance; only ".($amount - $remaining).' could be allocated. Pass explicit `allocations` to overpay a specific invoice, or reduce the amount.'],
            ]);
        }

        return $allocations;
    }
}
