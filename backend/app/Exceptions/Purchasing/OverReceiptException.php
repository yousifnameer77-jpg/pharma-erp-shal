<?php

namespace App\Exceptions\Purchasing;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A goods receipt line can never bring a purchase order item's cumulative
 * received_quantity above what was ordered — this is what makes "received"
 * status meaningful and stops a supplier being credited for stock nobody
 * ordered.
 */
class OverReceiptException extends Exception
{
    public function __construct(
        public readonly string $purchaseOrderItemId,
        public readonly float $ordered,
        public readonly float $alreadyReceived,
        public readonly float $attempted,
    ) {
        $remaining = $ordered - $alreadyReceived;
        parent::__construct("Cannot receive {$attempted}: only {$remaining} remains outstanding on this order line (ordered {$ordered}, already received {$alreadyReceived}).");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'over_receipt',
            'purchase_order_item_id' => $this->purchaseOrderItemId,
            'ordered' => $this->ordered,
            'already_received' => $this->alreadyReceived,
            'attempted' => $this->attempted,
        ], 422);
    }
}
