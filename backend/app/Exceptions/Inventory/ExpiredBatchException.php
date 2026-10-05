<?php

namespace App\Exceptions\Inventory;

use App\Models\Batch;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Raised only by the sale path. Every other movement type (transfer, damage,
 * expired write-off, adjustment) is explicitly allowed to touch an expired
 * batch — those are the mechanisms for moving expired stock into a
 * quarantine/returns warehouse or writing it off in the first place.
 */
class ExpiredBatchException extends Exception
{
    public function __construct(public readonly Batch $batch)
    {
        parent::__construct(
            "Batch {$batch->batch_number} expired on {$batch->expiry_date->toDateString()} and cannot be sold."
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'batch_expired',
            'batch_id' => $this->batch->id,
            'batch_number' => $this->batch->batch_number,
            'expiry_date' => $this->batch->expiry_date->toDateString(),
        ], 422);
    }
}
