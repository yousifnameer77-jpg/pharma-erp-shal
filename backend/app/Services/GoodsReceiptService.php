<?php

namespace App\Services;

use App\Exceptions\Purchasing\InvalidStatusTransitionException;
use App\Exceptions\Purchasing\OverReceiptException;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Receive Goods" — the step that actually ties Purchasing to Inventory and
 * Accounting. Creating a receipt just records what arrived (draft, no
 * effect yet); post() is what makes it real: for every line it calls
 * BatchService::receive() (the same entry point the Inventory module itself
 * uses), which atomically creates/tops-up the batch and records a
 * purchase_in stock movement — so a batch received this way is
 * indistinguishable from one received directly through the Inventory API.
 * It also advances the purchase order's received_quantity/status and posts
 * one journal entry (Dr Inventory, Cr GRNI) via JournalEntryService.
 */
class GoodsReceiptService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
        private readonly BatchService $batchService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    /**
     * @param  array{company_id: string, warehouse_id: string, purchase_order_id: string, receipt_date: string, notes?: ?string, items: array<int, array{purchase_order_item_id: string, batch_number: string, manufacture_date?: ?string, expiry_date: string, quantity: float, unit_cost: float}>}  $data
     */
    public function create(array $data, User $user): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $user) {
            $order = PurchaseOrder::whereKey($data['purchase_order_id'])->lockForUpdate()->firstOrFail();

            if (! in_array($order->status, ['approved', 'partially_received'], true)) {
                throw new InvalidStatusTransitionException('purchase_order', $order->id, $order->status, 'partially_received');
            }

            $receipt = GoodsReceipt::create([
                'company_id' => $data['company_id'],
                'warehouse_id' => $data['warehouse_id'],
                'purchase_order_id' => $order->id,
                'receipt_number' => $this->sequences->next($data['company_id'], 'goods_receipt', 'GR'),
                'receipt_date' => $data['receipt_date'],
                'status' => 'draft',
                'received_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $orderItem = PurchaseOrderItem::where('id', $item['purchase_order_item_id'])
                    ->where('purchase_order_id', $order->id)
                    ->firstOrFail();

                $quantity = (float) $item['quantity'];
                $remaining = (float) $orderItem->quantity - (float) $orderItem->received_quantity;

                if ($quantity > $remaining) {
                    throw new OverReceiptException($orderItem->id, (float) $orderItem->quantity, (float) $orderItem->received_quantity, $quantity);
                }

                $receipt->items()->create([
                    'purchase_order_item_id' => $orderItem->id,
                    'product_id' => $orderItem->product_id,
                    'batch_number' => $item['batch_number'],
                    'manufacture_date' => $item['manufacture_date'] ?? null,
                    'expiry_date' => $item['expiry_date'],
                    'quantity' => $quantity,
                    'unit_cost' => $item['unit_cost'],
                ]);
            }

            return $receipt->load(['items', 'purchaseOrder']);
        });
    }

    public function post(GoodsReceipt $receipt, User $user): GoodsReceipt
    {
        if ($receipt->status !== 'draft') {
            throw new InvalidStatusTransitionException('goods_receipt', $receipt->id, $receipt->status, 'posted');
        }

        return DB::transaction(function () use ($receipt, $user) {
            $receipt->loadMissing(['items.purchaseOrderItem', 'purchaseOrder']);
            $order = $receipt->purchaseOrder;
            $total = 0.0;

            foreach ($receipt->items as $item) {
                // Re-validated here, locked, rather than trusting the check already
                // done in create(): two draft receipts can each pass that check
                // against a stale read, so this is the check that actually has to
                // hold — the same discipline StockMovementService uses for stock.
                $orderItem = PurchaseOrderItem::whereKey($item->purchase_order_item_id)->lockForUpdate()->firstOrFail();
                $remaining = (float) $orderItem->quantity - (float) $orderItem->received_quantity;

                if ((float) $item->quantity > $remaining) {
                    throw new OverReceiptException($orderItem->id, (float) $orderItem->quantity, (float) $orderItem->received_quantity, (float) $item->quantity);
                }

                $this->batchService->receive([
                    'product_id' => $item->product_id,
                    'batch_number' => $item->batch_number,
                    'manufacture_date' => $item->manufacture_date?->toDateString(),
                    'expiry_date' => $item->expiry_date->toDateString(),
                    'supplier_id' => $order->supplier_id,
                    'purchase_price' => $item->unit_cost,
                    'warehouse_id' => $receipt->warehouse_id,
                    'quantity' => $item->quantity,
                    'reference_type' => 'goods_receipt',
                    'reference_id' => $receipt->id,
                ], $user);

                $orderItem->increment('received_quantity', (float) $item->quantity);

                $total += (float) $item->quantity * (float) $item->unit_cost;
            }

            $this->purchaseOrderService->recalculateReceiptStatus($order);

            // A zero-cost receipt (free goods, samples) has stock impact but no
            // accounting impact — a journal line requires a nonzero debit/credit,
            // so there's nothing valid to post.
            if ($total > 0) {
                $inventoryAccount = ChartOfAccount::findByCode($receipt->company_id, ChartOfAccount::CODE_INVENTORY);
                $grniAccount = ChartOfAccount::findByCode($receipt->company_id, ChartOfAccount::CODE_GRNI);

                $this->journalEntryService->post(
                    companyId: $receipt->company_id,
                    branchId: $order->branch_id,
                    entryDate: $receipt->receipt_date->toDateString(),
                    description: "Goods receipt {$receipt->receipt_number} against PO {$order->order_number}",
                    lines: [
                        ['account_id' => $inventoryAccount->id, 'debit' => $total, 'description' => 'Stock received'],
                        ['account_id' => $grniAccount->id, 'credit' => $total, 'description' => 'Goods received, not yet invoiced'],
                    ],
                    user: $user,
                    referenceType: 'goods_receipt',
                    referenceId: $receipt->id,
                );
            }

            $receipt->update(['status' => 'posted', 'posted_at' => now()]);

            return $receipt->fresh(['items', 'purchaseOrder']);
        });
    }
}
