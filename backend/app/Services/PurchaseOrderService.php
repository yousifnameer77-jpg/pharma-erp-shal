<?php

namespace App\Services;

use App\Exceptions\Purchasing\InvalidStatusTransitionException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(private readonly DocumentSequenceService $sequences)
    {
    }

    /**
     * @param  array{company_id: string, branch_id: string, warehouse_id: string, supplier_id: string, purchase_request_id?: ?string, order_date: string, expected_date?: ?string, notes?: ?string, items: array<int, array{product_id: string, quantity: float, unit_price: float, tax_rate?: float}>}  $data
     */
    public function create(array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $order = PurchaseOrder::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_request_id' => $data['purchase_request_id'] ?? null,
                'order_number' => $this->sequences->next($data['company_id'], 'purchase_order', 'PO'),
                'status' => 'draft',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                ]);
            }

            return $order->load('items');
        });
    }

    /**
     * Prices every line of an approved PurchaseRequest and turns it into a
     * new draft PurchaseOrder for one supplier, marking the request
     * `converted` — the Purchase Request -> Purchase Order step of the
     * workflow.
     *
     * @param  array<string, float>  $unitPrices  keyed by product_id
     */
    public function createFromRequest(PurchaseRequest $request, string $supplierId, array $unitPrices, User $user, ?string $expectedDate = null, ?string $notes = null): PurchaseOrder
    {
        if ($request->status !== 'approved') {
            throw new InvalidStatusTransitionException('purchase_request', $request->id, $request->status, 'converted');
        }

        return DB::transaction(function () use ($request, $supplierId, $unitPrices, $user, $expectedDate, $notes) {
            $items = $request->items->map(function ($item) use ($unitPrices) {
                if (! array_key_exists($item->product_id, $unitPrices)) {
                    throw ValidationException::withMessages([
                        'unit_prices' => ["A unit price is required for product {$item->product_id}."],
                    ]);
                }

                return [
                    'product_id' => $item->product_id,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => $unitPrices[$item->product_id],
                ];
            })->all();

            $order = $this->create([
                'company_id' => $request->company_id,
                'branch_id' => $request->branch_id,
                'warehouse_id' => $request->warehouse_id,
                'supplier_id' => $supplierId,
                'purchase_request_id' => $request->id,
                'order_date' => now()->toDateString(),
                'expected_date' => $expectedDate,
                'notes' => $notes,
                'items' => $items,
            ], $user);

            $request->update(['status' => 'converted']);

            return $order;
        });
    }

    /**
     * @param  array{expected_date?: ?string, notes?: ?string, items?: array<int, array{product_id: string, quantity: float, unit_price: float, tax_rate?: float}>}  $data
     */
    public function update(PurchaseOrder $order, array $data): PurchaseOrder
    {
        $this->guard($order, 'draft', 'update');

        return DB::transaction(function () use ($order, $data) {
            $order->update([
                'expected_date' => $data['expected_date'] ?? $order->expected_date,
                'notes' => $data['notes'] ?? $order->notes,
            ]);

            if (array_key_exists('items', $data)) {
                $order->items()->delete();
                foreach ($data['items'] as $item) {
                    $order->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'] ?? 0,
                    ]);
                }
            }

            return $order->fresh('items');
        });
    }

    public function submit(PurchaseOrder $order): PurchaseOrder
    {
        $this->guard($order, 'draft', 'submitted');
        $order->update(['status' => 'submitted']);

        return $order->fresh();
    }

    /** Approval is what unlocks receiving goods against this order. */
    public function approve(PurchaseOrder $order, User $user): PurchaseOrder
    {
        $this->guard($order, 'submitted', 'approved');
        $order->update(['status' => 'approved', 'approved_by' => $user->id]);

        return $order->fresh();
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (! in_array($order->status, ['draft', 'submitted', 'approved'], true)) {
            throw new InvalidStatusTransitionException('purchase_order', $order->id, $order->status, 'cancelled');
        }

        $order->update(['status' => 'cancelled']);

        return $order->fresh();
    }

    /**
     * Called by GoodsReceiptService after a receipt posts. Advances status
     * from the received quantities alone — never called for a status outside
     * the receivable range, so it never needs to move anything backwards.
     */
    public function recalculateReceiptStatus(PurchaseOrder $order): void
    {
        $order->loadMissing('items');
        $allReceived = $order->items->every(fn ($item) => (float) $item->received_quantity >= (float) $item->quantity);
        $anyReceived = $order->items->contains(fn ($item) => (float) $item->received_quantity > 0);

        $order->update(['status' => $allReceived ? 'received' : ($anyReceived ? 'partially_received' : $order->status)]);
    }

    private function guard(PurchaseOrder $order, string $expected, string $to): void
    {
        if ($order->status !== $expected) {
            throw new InvalidStatusTransitionException('purchase_order', $order->id, $order->status, $to);
        }
    }
}
