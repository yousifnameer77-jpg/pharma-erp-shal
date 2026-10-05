<?php

namespace App\Exceptions\Sales;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Raised whenever a Sales document's status workflow is violated — e.g.
 * posting a sales invoice that isn't draft, or allocating a payment against
 * an invoice that isn't posted/partially_paid. Mirrors
 * App\Exceptions\Purchasing\InvalidStatusTransitionException for the Sales
 * side of the app.
 */
class InvalidStatusTransitionException extends Exception
{
    public function __construct(
        public readonly string $documentType,
        public readonly string $documentId,
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct("Cannot move {$documentType} {$documentId} from status '{$from}' to '{$to}'.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_status_transition',
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'from' => $this->from,
            'to' => $this->to,
        ], 422);
    }
}
