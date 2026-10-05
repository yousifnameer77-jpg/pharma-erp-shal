<?php

namespace App\Services;

use App\Exceptions\Sales\InvalidStatusTransitionException;
use App\Exceptions\Sales\OverReturnException;
use App\Models\Batch;
use App\Models\ChartOfAccount;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Returns" step. Reverses a sale: stock goes back to a named batch via
 * StockMovementService::recordCustomerReturn() (a return always names its
 * batch — no FEFO here, since the whole point is putting stock back where it
 * came from), and the journal entry is the exact mirror of the sale's — Dr
 * Revenue/Tax, Cr Receivable for the sale value, Cr COGS/Dr Inventory for
 * the cost.
 *
 * Documented simplification: a posted return lowers the customer's overall
 * balance (see CustomerAccountService) but does not itself reduce a specific
 * invoice's `paid_amount`/remaining_due — those still track payments only.
 * Crediting a specific invoice for a return would be a natural next step
 * (a "credit note" that can be allocated like a payment) — not implemented.
 */
class SalesReturnService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly StockMovementService $stockMovementService,
        private readonly JournalEntryService $journalEntryService,
        private readonly ExchangeRateService $fx,
    ) {
    }

    /**
     * @param  array{company_id: string, warehouse_id: string, customer_id: string, sales_invoice_id?: ?string, return_date: string, notes?: ?string, items: array<int, array{sales_invoice_item_id?: ?string, product_id: string, batch_id: string, quantity: float, unit_price: float, tax_rate?: float}>}  $data
     */
    public function create(array $data, User $user): SalesReturn
    {
        return DB::transaction(function () use ($data, $user) {
            $return = SalesReturn::create([
                'company_id' => $data['company_id'],
                'warehouse_id' => $data['warehouse_id'],
                'customer_id' => $data['customer_id'],
                'sales_invoice_id' => $data['sales_invoice_id'] ?? null,
                'return_number' => $this->sequences->next($data['company_id'], 'sales_return', 'SR'),
                'return_date' => $data['return_date'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $subtotal = 0.0;
            $taxAmount = 0.0;

            foreach ($data['items'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $taxRate = (float) ($item['tax_rate'] ?? 0);
                $lineNet = $quantity * $unitPrice;
                $lineTax = $lineNet * $taxRate / 100;

                $return->items()->create([
                    'sales_invoice_item_id' => $item['sales_invoice_item_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'batch_id' => $item['batch_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineNet + $lineTax,
                ]);

                $subtotal += $lineNet;
                $taxAmount += $lineTax;
            }

            $return->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount,
            ]);

            return $return->fresh('items');
        });
    }

    public function post(SalesReturn $return, User $user): SalesReturn
    {
        if ($return->status !== 'draft') {
            throw new InvalidStatusTransitionException('sales_return', $return->id, $return->status, 'posted');
        }

        return DB::transaction(function () use ($return, $user) {
            $return->loadMissing('items');
            $movements = collect();

            foreach ($return->items as $item) {
                if ($item->sales_invoice_item_id) {
                    // Re-validated here, locked, rather than trusting a stale read —
                    // the same discipline GoodsReceiptService uses for over-receipt.
                    $invoiceItem = SalesInvoiceItem::whereKey($item->sales_invoice_item_id)->lockForUpdate()->firstOrFail();
                    $remaining = (float) $invoiceItem->quantity - (float) $invoiceItem->returned_quantity;

                    if ((float) $item->quantity > $remaining) {
                        throw new OverReturnException($invoiceItem->id, (float) $invoiceItem->quantity, (float) $invoiceItem->returned_quantity, (float) $item->quantity);
                    }

                    $invoiceItem->increment('returned_quantity', (float) $item->quantity);
                }

                $movement = $this->stockMovementService->recordCustomerReturn([
                    'batch_id' => $item->batch_id,
                    'warehouse_id' => $return->warehouse_id,
                    'quantity' => (float) $item->quantity,
                    'reference_type' => 'sales_return_item',
                    'reference_id' => $item->id,
                    'notes' => "Sales return {$return->return_number}",
                ], $user);

                $movements->push($movement);
            }

            $totalCost = $this->costOfMovements($movements);

            // A return against a foreign-currency invoice is in that currency
            // and reverses the sale at the invoice's own rate.
            $rate = $return->sales_invoice_id
                ? (float) \App\Models\SalesInvoice::whereKey($return->sales_invoice_id)->value('exchange_rate')
                : 1.0;
            $subtotalBase = $this->fx->toBase((float) $return->subtotal, $rate);
            $taxBase = $this->fx->toBase((float) $return->tax_amount, $rate);

            $lines = [];
            $arAccount = ChartOfAccount::findByCode($return->company_id, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);
            $lines[] = ['account_id' => $arAccount->id, 'credit' => $subtotalBase + $taxBase, 'description' => 'Accounts receivable reduced'];

            if ((float) $return->subtotal > 0) {
                $revenueAccount = ChartOfAccount::findByCode($return->company_id, ChartOfAccount::CODE_REVENUE);
                $lines[] = ['account_id' => $revenueAccount->id, 'debit' => $subtotalBase, 'description' => 'Sales revenue reversed'];
            }

            if ((float) $return->tax_amount > 0) {
                $taxAccount = ChartOfAccount::findByCode($return->company_id, ChartOfAccount::CODE_TAX_OUTPUT);
                $lines[] = ['account_id' => $taxAccount->id, 'debit' => $taxBase, 'description' => 'Sales tax payable reversed'];
            }

            if ($totalCost > 0) {
                $cogsAccount = ChartOfAccount::findByCode($return->company_id, ChartOfAccount::CODE_COGS);
                $inventoryAccount = ChartOfAccount::findByCode($return->company_id, ChartOfAccount::CODE_INVENTORY);
                $lines[] = ['account_id' => $inventoryAccount->id, 'debit' => $totalCost, 'description' => 'Stock returned'];
                $lines[] = ['account_id' => $cogsAccount->id, 'credit' => $totalCost, 'description' => 'Cost of goods sold reversed'];
            }

            $this->journalEntryService->post(
                companyId: $return->company_id,
                branchId: null,
                entryDate: $return->return_date->toDateString(),
                description: "Sales return {$return->return_number}",
                lines: $lines,
                user: $user,
                referenceType: 'sales_return',
                referenceId: $return->id,
            );

            $return->update(['status' => 'posted', 'posted_at' => now()]);

            return $return->fresh('items');
        });
    }

    public function cancel(SalesReturn $return): SalesReturn
    {
        if ($return->status !== 'draft') {
            throw new InvalidStatusTransitionException('sales_return', $return->id, $return->status, 'cancelled');
        }

        $return->update(['status' => 'cancelled']);

        return $return->fresh();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\StockMovement>  $movements
     */
    private function costOfMovements($movements): float
    {
        $batchCosts = Batch::whereIn('id', $movements->pluck('batch_id')->unique())->pluck('purchase_price', 'id');

        return (float) $movements->sum(fn ($movement) => (float) $movement->quantity * (float) ($batchCosts[$movement->batch_id] ?? 0));
    }
}
