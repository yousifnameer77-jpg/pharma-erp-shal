<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockMovement\AdjustmentStockRequest;
use App\Http\Requests\StockMovement\CustomerReturnStockRequest;
use App\Http\Requests\StockMovement\DamageStockRequest;
use App\Http\Requests\StockMovement\ExpiredStockRequest;
use App\Http\Requests\StockMovement\SellStockRequest;
use App\Http\Requests\StockMovement\SupplierReturnStockRequest;
use App\Http\Requests\StockMovement\TransferStockRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The stock_movements ledger is append-only: this controller deliberately
 * exposes no update/destroy route (StockMovement itself also refuses those
 * at the model level — see StockMovement::booted()). To correct a mistake,
 * record a compensating movement instead of editing history.
 */
class StockMovementController extends Controller
{
    public function __construct(private readonly StockMovementService $stockMovementService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $movements = StockMovement::query()
            ->with(['batch.product', 'fromWarehouse', 'toWarehouse', 'performedBy'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('batch_id', $request->query('batch_id')))
            ->when($request->filled('product_id'), function ($q) use ($request) {
                $q->whereHas('batch', fn ($b) => $b->where('product_id', $request->query('product_id')));
            })
            ->when($request->filled('warehouse_id'), function ($q) use ($request) {
                $warehouseId = $request->query('warehouse_id');
                $q->where(fn ($q2) => $q2->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId));
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('date_to')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 30));

        return response()->json(StockMovementResource::collection($movements)->response()->getData(true));
    }

    public function show(StockMovement $stockMovement): JsonResponse
    {
        $stockMovement->load(['batch.product', 'fromWarehouse', 'toWarehouse', 'performedBy']);

        return response()->json(['data' => new StockMovementResource($stockMovement)]);
    }

    public function sell(SellStockRequest $request): JsonResponse
    {
        $movements = $this->stockMovementService->recordSale($request->validated(), $request->user());

        return response()->json(['data' => StockMovementResource::collection($movements)], 201);
    }

    public function transfer(TransferStockRequest $request): JsonResponse
    {
        $movement = $this->stockMovementService->recordTransfer($request->validated(), $request->user());

        return response()->json(['data' => new StockMovementResource($movement)], 201);
    }

    public function customerReturn(CustomerReturnStockRequest $request): JsonResponse
    {
        $movement = $this->stockMovementService->recordCustomerReturn($request->validated(), $request->user());

        return response()->json(['data' => new StockMovementResource($movement)], 201);
    }

    public function supplierReturn(SupplierReturnStockRequest $request): JsonResponse
    {
        $movement = $this->stockMovementService->recordSupplierReturn($request->validated(), $request->user());

        return response()->json(['data' => new StockMovementResource($movement)], 201);
    }

    public function damage(DamageStockRequest $request): JsonResponse
    {
        $movement = $this->stockMovementService->recordDamage($request->validated(), $request->user());

        return response()->json(['data' => new StockMovementResource($movement)], 201);
    }

    public function expired(ExpiredStockRequest $request): JsonResponse
    {
        $movement = $this->stockMovementService->recordExpired($request->validated(), $request->user());

        return response()->json(['data' => new StockMovementResource($movement)], 201);
    }

    public function adjustment(AdjustmentStockRequest $request): JsonResponse
    {
        $movement = $this->stockMovementService->recordAdjustment($request->validated(), $request->user());

        return response()->json(['data' => new StockMovementResource($movement)], 201);
    }
}
