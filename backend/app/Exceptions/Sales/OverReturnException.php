<?php

namespace App\Exceptions\Sales;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A return line traced back to a specific sales_invoice_item can never bring
 * that line's cumulative returned_quantity above what was actually sold on
 * it — the sales-side mirror of Purchasing's OverReceiptException.
 */
class OverReturnException extends Exception
{
    public function __construct(
        public readonly string $salesInvoiceItemId,
        public readonly float $sold,
        public readonly float $alreadyReturned,
        public readonly float $attempted,
    ) {
        $remaining = $sold - $alreadyReturned;
        parent::__construct("Cannot return {$attempted}: only {$remaining} remains returnable on this invoice line (sold {$sold}, already returned {$alreadyReturned}).");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'over_return',
            'sales_invoice_item_id' => $this->salesInvoiceItemId,
            'sold' => $this->sold,
            'already_returned' => $this->alreadyReturned,
            'attempted' => $this->attempted,
        ], 422);
    }
}
