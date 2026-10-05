<?php

namespace App\Exceptions\Purchasing;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Raised whenever a Purchasing document's status workflow is violated — e.g.
 * approving a purchase order that isn't submitted, or posting a goods
 * receipt that isn't in draft. $documentType/$documentId identify the
 * record; $from/$to describe the rejected transition.
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
