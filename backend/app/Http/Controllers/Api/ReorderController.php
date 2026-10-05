<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseRequestResource;
use App\Services\ReorderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReorderController extends Controller
{
    public function __construct(private readonly ReorderService $reorderService)
    {
    }

    public function suggestions(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');
        $branchId = $request->query('branch_id');

        $data = $this->reorderService->getSuggestions(
            warehouseId: $warehouseId,
            branchId: $branchId
        );

        return response()->json([
            'data' => $data,
        ]);
    }

    public function createRequest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $purchaseRequest = $this->reorderService->generatePurchaseRequest(
            items: $validated['items'],
            user: $request->user(),
            warehouseId: $validated['warehouse_id'] ?? null,
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'message' => "Purchase request {$purchaseRequest->request_number} generated successfully.",
            'data' => new PurchaseRequestResource($purchaseRequest->load(['items.product', 'warehouse', 'branch'])),
        ], 201);
    }
}

