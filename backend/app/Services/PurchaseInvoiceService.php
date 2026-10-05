<?php

namespace App\Services;

use App\Exceptions\Purchasing\InvalidStatusTransitionException;
use App\Models\ChartOfAccount;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Invoice" step of the workflow. A draft invoice has no accounting effect;
 * post() is what creates the Accounts Payable liability (and is what makes
 * the invoice count toward the supplier's balance — see
 * SupplierAccountService).
 *
 * Simplification, documented rather than hidden: this assumes an invoice's
 * line prices match what was booked at goods receipt, so a goods-receipt-
 * linked invoice simply clears GRNI at its own subtotal. A real deployment
 * with purchase-price variance would book the difference to a variance
 * account here — not yet implemented.
 */
class PurchaseInvoiceService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly JournalEntryService $journalEntryService,
        private readonly ExchangeRateService $fx,
    ) {
    }

    /**
     * @param  array{company_id: string, supplier_id: string, purchase_order_id?: ?string, goods_receipt_id?: ?string, supplier_invoice_number?: ?string, invoice_date: string, due_date?: ?string, notes?: ?string, items: array<int, array{product_id: string, quantity: float, unit_price: float, tax_rate?: float}>}  $data
     */
    public function create(array $data, User $user): PurchaseInvoice
    {
        return DB::transaction(function () use ($data, $user) {
            $currency = $data['currency'] ?? ExchangeRateService::BASE;

            $invoice = PurchaseInvoice::create([
                'currency' => $currency,
                'exchange_rate' => $this->fx->rateFor($data['company_id'], $currency, $data['invoice_date']),
                'company_id' => $data['company_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'invoice_number' => $this->sequences->next($data['company_id'], 'purchase_invoice', 'INV'),
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->syncItems($invoice, $data['items']);

            return $invoice->fresh('items');
        });
    }

    /**
     * @param  array{supplier_invoice_number?: ?string, due_date?: ?string, notes?: ?string, items?: array<int, array{product_id: string, quantity: float, unit_price: float, tax_rate?: float}>}  $data
     */
    public function update(PurchaseInvoice $invoice, array $data): PurchaseInvoice
    {
        $this->guard($invoice, 'draft', 'update');

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? $invoice->supplier_invoice_number,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'notes' => $data['notes'] ?? $invoice->notes,
            ]);

            if (array_key_exists('items', $data)) {
                $invoice->items()->delete();
                $this->syncItems($invoice, $data['items']);
            }

            return $invoice->fresh('items');
        });
    }

    public function post(PurchaseInvoice $invoice, User $user): PurchaseInvoice
    {
        $this->guard($invoice, 'draft', 'posted');

        return DB::transaction(function () use ($invoice, $user) {
            $invoice = PurchaseInvoice::with('items')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            // Ledger is always IQD: convert at the invoice rate; payable is
            // the sum of the converted parts so the entry balances exactly.
            $rate = (float) $invoice->exchange_rate;
            $subtotalBase = $this->fx->toBase((float) $invoice->subtotal, $rate);
            $taxBase = $this->fx->toBase((float) $invoice->tax_amount, $rate);

            $lines = [];

            $clearingAccount = $invoice->goods_receipt_id
                ? ChartOfAccount::findByCode($invoice->company_id, ChartOfAccount::CODE_GRNI)
                : ChartOfAccount::findByCode($invoice->company_id, ChartOfAccount::CODE_INVENTORY);

            if ((float) $invoice->subtotal > 0) {
                $lines[] = ['account_id' => $clearingAccount->id, 'debit' => $subtotalBase, 'description' => 'Invoice subtotal'];
            }

            if ((float) $invoice->tax_amount > 0) {
                $taxAccount = ChartOfAccount::findByCode($invoice->company_id, ChartOfAccount::CODE_TAX_INPUT);
                $lines[] = ['account_id' => $taxAccount->id, 'debit' => $taxBase, 'description' => 'Purchase tax input'];
            }

            $payableAccount = ChartOfAccount::findByCode($invoice->company_id, ChartOfAccount::CODE_ACCOUNTS_PAYABLE);
            $lines[] = ['account_id' => $payableAccount->id, 'credit' => $subtotalBase + $taxBase, 'description' => 'Accounts payable'];

            if ($lines !== []) {
                $this->journalEntryService->post(
                    companyId: $invoice->company_id,
                    branchId: null,
                    entryDate: $invoice->invoice_date->toDateString(),
                    description: "Purchase invoice {$invoice->invoice_number}",
                    lines: $lines,
                    user: $user,
                    referenceType: 'purchase_invoice',
                    referenceId: $invoice->id,
                );
            }

            $invoice->update(['status' => 'posted', 'posted_at' => now()]);

            return $invoice->fresh('items');
        });
    }

    public function cancel(PurchaseInvoice $invoice): PurchaseInvoice
    {
        // Posted invoices already have a journal entry and possibly payments —
        // cancelling one would desync the ledger, so only a draft can be dropped.
        $this->guard($invoice, 'draft', 'cancelled');
        $invoice->update(['status' => 'cancelled']);

        return $invoice->fresh();
    }

    /**
     * @param  array<int, array{product_id: string, quantity: float, unit_price: float, tax_rate?: float}>  $items
     */
    private function syncItems(PurchaseInvoice $invoice, array $items): void
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * $taxRate / 100;

            $invoice->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'line_total' => $lineSubtotal + $lineTax,
            ]);

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal + $taxAmount,
        ]);
    }

    private function guard(PurchaseInvoice $invoice, string $expected, string $to): void
    {
        if ($invoice->status !== $expected) {
            throw new InvalidStatusTransitionException('purchase_invoice', $invoice->id, $invoice->status, $to);
        }
    }
}
