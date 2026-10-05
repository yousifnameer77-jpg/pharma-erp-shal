<?php

namespace App\Services;

use App\Exceptions\Sales\InvalidStatusTransitionException;
use App\Models\Batch;
use App\Models\ChartOfAccount;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Sales Invoice" step. A draft invoice has no stock or accounting effect;
 * post() is what makes the sale real, all in one transaction:
 *  - debits stock for every line via StockMovementService::recordSale() —
 *    the exact entry point Inventory itself uses, so FEFO batch selection,
 *    "can't sell more than exists" and "can't sell an expired batch" are
 *    inherited for free, not reimplemented here;
 *  - posts one journal entry recognizing revenue, tax payable, receivable,
 *    and the cost of goods sold;
 *  - the invoice then counts toward the customer's balance (see
 *    CustomerAccountService) until CustomerPaymentService reduces it.
 */
class SalesInvoiceService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly StockMovementService $stockMovementService,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    /**
     * @param  array{company_id: string, branch_id: string, warehouse_id: string, customer_id: string, invoice_date: string, due_date?: ?string, notes?: ?string, items: array<int, array{product_id: string, batch_id?: ?string, quantity: float, unit_price: float, tax_rate?: float, discount_rate?: float}>}  $data
     */
    public function create(array $data, User $user): SalesInvoice
    {
        return DB::transaction(function () use ($data, $user) {
            $invoice = SalesInvoice::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'warehouse_id' => $data['warehouse_id'],
                'customer_id' => $data['customer_id'],
                'invoice_number' => $this->sequences->next($data['company_id'], 'sales_invoice', 'SI'),
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
     * @param  array{due_date?: ?string, notes?: ?string, items?: array<int, array{product_id: string, batch_id?: ?string, quantity: float, unit_price: float, tax_rate?: float, discount_rate?: float}>}  $data
     */
    public function update(SalesInvoice $invoice, array $data): SalesInvoice
    {
        $this->guard($invoice, 'draft', 'update');

        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
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

    public function post(SalesInvoice $invoice, User $user): SalesInvoice
    {
        $this->guard($invoice, 'draft', 'posted');

        return DB::transaction(function () use ($invoice, $user) {
            $invoice = SalesInvoice::with('items')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $allMovements = collect();

            foreach ($invoice->items as $item) {
                $movements = $this->stockMovementService->recordSale([
                    'product_id' => $item->product_id,
                    'batch_id' => $item->batch_id,
                    'warehouse_id' => $invoice->warehouse_id,
                    'quantity' => (float) $item->quantity,
                    'reference_type' => 'sales_invoice_item',
                    'reference_id' => $item->id,
                    'notes' => "Sales invoice {$invoice->invoice_number}",
                ], $user);

                $allMovements = $allMovements->merge($movements);
            }

            $totalCost = $this->costOfMovements($allMovements);
            $netRevenue = (float) $invoice->subtotal - (float) $invoice->discount_amount;

            $lines = [];
            $arAccount = ChartOfAccount::findByCode($invoice->company_id, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);
            $lines[] = ['account_id' => $arAccount->id, 'debit' => (float) $invoice->total_amount, 'description' => 'Accounts receivable'];

            if ($netRevenue > 0) {
                $revenueAccount = ChartOfAccount::findByCode($invoice->company_id, ChartOfAccount::CODE_REVENUE);
                $lines[] = ['account_id' => $revenueAccount->id, 'credit' => $netRevenue, 'description' => 'Sales revenue'];
            }

            if ((float) $invoice->tax_amount > 0) {
                $taxAccount = ChartOfAccount::findByCode($invoice->company_id, ChartOfAccount::CODE_TAX_OUTPUT);
                $lines[] = ['account_id' => $taxAccount->id, 'credit' => (float) $invoice->tax_amount, 'description' => 'Sales tax payable'];
            }

            if ($totalCost > 0) {
                $cogsAccount = ChartOfAccount::findByCode($invoice->company_id, ChartOfAccount::CODE_COGS);
                $inventoryAccount = ChartOfAccount::findByCode($invoice->company_id, ChartOfAccount::CODE_INVENTORY);
                $lines[] = ['account_id' => $cogsAccount->id, 'debit' => $totalCost, 'description' => 'Cost of goods sold'];
                $lines[] = ['account_id' => $inventoryAccount->id, 'credit' => $totalCost, 'description' => 'Stock sold'];
            }

            $this->journalEntryService->post(
                companyId: $invoice->company_id,
                branchId: $invoice->branch_id,
                entryDate: $invoice->invoice_date->toDateString(),
                description: "Sales invoice {$invoice->invoice_number}",
                lines: $lines,
                user: $user,
                referenceType: 'sales_invoice',
                referenceId: $invoice->id,
            );

            $invoice->update(['status' => 'posted', 'posted_at' => now()]);

            return $invoice->fresh('items');
        });
    }

    public function cancel(SalesInvoice $invoice): SalesInvoice
    {
        // Posted invoices already moved stock and have a journal entry — only a
        // draft can be dropped, matching PurchaseInvoiceService::cancel().
        $this->guard($invoice, 'draft', 'cancelled');
        $invoice->update(['status' => 'cancelled']);

        return $invoice->fresh();
    }

    /**
     * Cost basis is each drawn batch's purchase_price (its cost at receipt) —
     * a batch received with no cost recorded contributes zero, a documented
     * simplification (see the README).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\StockMovement>  $movements
     */
    private function costOfMovements($movements): float
    {
        $batchCosts = Batch::whereIn('id', $movements->pluck('batch_id')->unique())->pluck('purchase_price', 'id');

        return (float) $movements->sum(fn ($movement) => (float) $movement->quantity * (float) ($batchCosts[$movement->batch_id] ?? 0));
    }

    /**
     * @param  array<int, array{product_id: string, batch_id?: ?string, quantity: float, unit_price: float, tax_rate?: float, discount_rate?: float}>  $items
     */
    private function syncItems(SalesInvoice $invoice, array $items): void
    {
        $subtotal = 0.0;
        $discountAmount = 0.0;
        $taxAmount = 0.0;

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $discountRate = (float) ($item['discount_rate'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);

            $lineBase = $quantity * $unitPrice;
            $lineDiscount = $lineBase * $discountRate / 100;
            $lineNet = $lineBase - $lineDiscount;
            $lineTax = $lineNet * $taxRate / 100;

            $invoice->items()->create([
                'product_id' => $item['product_id'],
                'batch_id' => $item['batch_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'discount_rate' => $discountRate,
                'line_total' => $lineNet + $lineTax,
            ]);

            $subtotal += $lineBase;
            $discountAmount += $lineDiscount;
            $taxAmount += $lineTax;
        }

        $invoice->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $subtotal - $discountAmount + $taxAmount,
        ]);
    }

    private function guard(SalesInvoice $invoice, string $expected, string $to): void
    {
        if ($invoice->status !== $expected) {
            throw new InvalidStatusTransitionException('sales_invoice', $invoice->id, $invoice->status, $to);
        }
    }
}
