<?php

namespace App\Exceptions\Accounting;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Raised when an Expense's status workflow is violated — e.g. posting or
 * editing one that isn't draft. Mirrors
 * App\Exceptions\Purchasing\InvalidStatusTransitionException /
 * App\Exceptions\Sales\InvalidStatusTransitionException for the Accounting
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
