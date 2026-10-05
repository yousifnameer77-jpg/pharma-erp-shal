<?php

namespace App\Exceptions\Purchasing;

use App\Models\SupplierPayment;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Raised by SupplierPayment::booted()'s updating()/deleting() guards. A
 * payment posts its journal entry and its invoice allocations atomically at
 * creation (see SupplierPaymentService::record()), so editing or deleting it
 * afterward would silently desync the ledger and the supplier balance —
 * record a refund/reversal as a new transaction instead.
 */
class SupplierPaymentImmutableException extends Exception
{
    public function __construct(public readonly SupplierPayment $payment)
    {
        parent::__construct("Supplier payment {$payment->payment_number} is posted and cannot be modified or deleted.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'supplier_payment_immutable',
            'supplier_payment_id' => $this->payment->id,
        ], 422);
    }
}
