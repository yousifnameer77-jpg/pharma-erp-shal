<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrderService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->query('supplier_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(PurchaseOrderResource::collection($orders)->response()->getData(true));
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $order = $this->purchaseOrderService->create($request->validated(), $request->user());

        return response()->json(['data' => new PurchaseOrderResource($order)], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder->load(['supplier', 'items.product']))]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->update($purchaseOrder, $request->validated());

        return response()->json(['data' => new PurchaseOrderResource($purchaseOrder)]);
    }

    public function submit(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['data' => new PurchaseOrderResource($this->purchaseOrderService->submit($purchaseOrder))]);
    }

    /** Approval is what unlocks receiving goods against this order. */
    public function approve(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['data' => new PurchaseOrderResource($this->purchaseOrderService->approve($purchaseOrder, $request->user()))]);
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['data' => new PurchaseOrderResource($this->purchaseOrderService->cancel($purchaseOrder))]);
    }
}
