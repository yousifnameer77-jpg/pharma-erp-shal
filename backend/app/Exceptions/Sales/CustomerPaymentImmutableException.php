<?php

namespace App\Exceptions\Sales;

use App\Models\CustomerPayment;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Raised by CustomerPayment::booted()'s updating()/deleting() guards —
 * mirrors SupplierPaymentImmutableException. A payment posts its journal
 * entry and its invoice allocations atomically at creation (see
 * CustomerPaymentService::record()), so editing or deleting it afterward
 * would silently desync the ledger and the customer balance — record a
 * refund as a new transaction instead.
 */
class CustomerPaymentImmutableException extends Exception
{
    public function __construct(public readonly CustomerPayment $payment)
    {
        parent::__construct("Customer payment {$payment->payment_number} is posted and cannot be modified or deleted.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'customer_payment_immutable',
            'customer_payment_id' => $this->payment->id,
        ], 422);
    }
}
