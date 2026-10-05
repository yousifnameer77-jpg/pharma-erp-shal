<?php

namespace App\Exceptions\Inventory;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Thrown whenever a movement would take quantity_on_hand below zero for a
 * batch/warehouse, or when the total available across all FEFO-eligible
 * batches of a product can't cover a requested sale. Always raised from
 * inside the same DB transaction that holds the row lock, so it reflects
 * the true, serialized stock level — not a stale read.
 */
class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly ?string $productId,
        public readonly ?string $batchId,
        public readonly string $warehouseId,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct("Insufficient stock: requested {$requested}, only {$available} available.");
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'error' => 'insufficient_stock',
            'product_id' => $this->productId,
            'batch_id' => $this->batchId,
            'warehouse_id' => $this->warehouseId,
            'requested' => $this->requested,
            'available' => $this->available,
        ], fn ($value) => $value !== null), 422);
    }
}
