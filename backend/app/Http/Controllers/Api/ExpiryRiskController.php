<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\ExpiryRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpiryRiskController extends Controller
{
    public function __construct(private readonly ExpiryRiskService $expiryRiskService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $daysThreshold = (int) $request->query('days_threshold', 180);
        $riskLevel = $request->query('risk_level');
        $branchId = $request->query('branch_id');
        $warehouseId = $request->query('warehouse_id');

        $data = $this->expiryRiskService->getExpiryRisk(
            branchId: $branchId,
            warehouseId: $warehouseId,
            riskLevel: $riskLevel,
            daysThreshold: $daysThreshold
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    public function quarantine(Request $request, Batch $batch): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->expiryRiskService->quarantineBatch(
            batchId: $batch->id,
            fromWarehouseId: $validated['from_warehouse_id'],
            quantity: (float) $validated['quantity'],
            user: $request->user(),
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Batch successfully moved to quarantine warehouse.',
            'data' => $result,
        ]);
    }
}

