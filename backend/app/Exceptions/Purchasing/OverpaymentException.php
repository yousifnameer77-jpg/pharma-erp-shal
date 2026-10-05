<?php

namespace App\Exceptions\Purchasing;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * A payment allocation can never exceed what's still owed on that invoice
 * (total_amount - paid_amount) — this is what keeps the supplier balance
 * (sum of every unpaid invoice) from ever going negative.
 */
class OverpaymentException extends Exception
{
    public function __construct(
        public readonly string $purchaseInvoiceId,
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
            'purchase_invoice_id' => $this->purchaseInvoiceId,
            'remaining_due' => $this->remainingDue,
            'attempted' => $this->attempted,
        ], 422);
    }
}
