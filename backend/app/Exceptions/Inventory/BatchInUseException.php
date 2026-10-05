<?php

namespace App\Exceptions\Inventory;

use App\Models\Batch;
use Exception;
use Illuminate\Http\JsonResponse;

class BatchInUseException extends Exception
{
    public function __construct(public readonly Batch $batch)
    {
        parent::__construct("Batch {$batch->batch_number} has stock movements recorded against it and cannot be deleted.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'batch_in_use',
            'batch_id' => $this->batch->id,
        ], 422);
    }
}
