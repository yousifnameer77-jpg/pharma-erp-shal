<?php

namespace App\Exceptions\Sales;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A payment allocation can never exceed what's still owed on that invoice
 * (total_amount - paid_amount) — the sales-side mirror of Purchasing's
 * OverpaymentException.
 */
class OverpaymentException extends Exception
{
    public function __construct(
        public readonly string $salesInvoiceId,
        public readonly float $remainingDue,
        public readonly float $attempted,
    ) {
        parent::__construct("Cannot allocate {$attempted}: only {$remainingDue} remains due on this invoice.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'overpayment',
            'sales_invoice_id' => $this->salesInvoiceId,
            'remaining_due' => $this->remainingDue,
            'attempted' => $this->attempted,
        ], 422);
    }
}
