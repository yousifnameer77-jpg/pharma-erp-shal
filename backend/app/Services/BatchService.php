<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchService
{
    public function __construct(private readonly StockMovementService $stockMovementService)
    {
    }

    /**
     * Receive stock for a product: finds the batch by (product_id,
     * batch_number) or creates it, then records a purchase_in movement for
     * the received quantity — batch identity and its first stock are always
     * created together, in one transaction, so a batch never exists with no
     * movement behind it.
     */
    public function receive(array $data, User $user): Batch
    {
        return DB::transaction(function () use ($data, $user) {
            $batch = Batch::firstOrCreate(
                ['product_id' => $data['product_id'], 'batch_number' => $data['batch_number']],
                [
                    'manufacture_date' => $data['manufacture_date'] ?? null,
                    'expiry_date' => $data['expiry_date'],
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'purchase_price' => $data['purchase_price'] ?? null,
                ],
            );

            if (! $batch->wasRecentlyCreated && $batch->expiry_date->toDateString() !== $data['expiry_date']) {
                throw ValidationException::withMessages([
                    'expiry_date' => ["Batch {$data['batch_number']} already exists for this product with expiry date {$batch->expiry_date->toDateString()}. Use that date, or a different batch number if this is really a different batch."],
                ]);
            }

            $this->stockMovementService->recordPurchase([
                'batch_id' => $batch->id,
                'warehouse_id' => $data['warehouse_id'],
                'quantity' => $data['quantity'],
                'unit_cost' => $data['purchase_price'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
            ], $user);

            return $batch->fresh(['product', 'supplier']);
        });
    }
}
