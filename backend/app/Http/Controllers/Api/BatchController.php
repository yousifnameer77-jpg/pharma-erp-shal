<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Batch\StoreBatchRequest;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function __construct(private readonly BatchService $batchService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $batches = Batch::query()
            ->with(['product', 'supplier'])
            ->withSum('stock as stock_sum_quantity_on_hand', 'quantity_on_hand')
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->query('product_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->query('supplier_id')))
            ->when($request->boolean('expired'), fn ($q) => $q->expired())
            ->when($request->filled('expiring_within_days'), fn ($q) => $q->expiringWithin($request->integer('expiring_within_days')))
            ->orderBy('expiry_date')
            ->paginate($request->integer('per_page', 20));

        return response()->json(BatchResource::collection($batches)->response()->getData(true));
    }

    /**
     * Receiving stock: creates the batch if it doesn't exist yet (or tops up
     * an existing one) and records the purchase_in movement, atomically.
     */
    public function store(StoreBatchRequest $request): JsonResponse
    {
        $batch = $this->batchService->receive($request->validated(), $request->user());

        return response()->json(['data' => new BatchResource($batch)], 201);
    }

    public function show(Batch $batch): JsonResponse
    {
        return response()->json(['data' => new BatchResource($batch->load(['product', 'supplier', 'stock.warehouse']))]);
    }
}
