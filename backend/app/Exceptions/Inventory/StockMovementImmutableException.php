<?php

namespace App\Exceptions\Inventory;

use App\Models\StockMovement;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Raised by StockMovement::booted()'s updating()/deleting() guards. This is
 * the backstop: the API doesn't expose an update/destroy route for stock
 * movements at all, but this makes the ledger's immutability a model-level
 * invariant that holds even for code (a future controller, a console
 * command, tinker) that tries to bypass the route layer.
 */
class StockMovementImmutableException extends Exception
{
    public function __construct(public readonly StockMovement $movement)
    {
        parent::__construct("Stock movement {$movement->id} is posted and cannot be modified or deleted. Record a compensating movement instead.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'stock_movement_immutable',
            'stock_movement_id' => $this->movement->id,
        ], 422);
    }
}
