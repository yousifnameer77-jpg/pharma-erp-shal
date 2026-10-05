<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseRequest\ConvertPurchaseRequestRequest;
use App\Http\Requests\PurchaseRequest\StorePurchaseRequestRequest;
use App\Http\Requests\PurchaseRequest\UpdatePurchaseRequestRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $purchaseRequestService,
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $requests = PurchaseRequest::query()
            ->with(['branch', 'warehouse', 'requestedBy'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->query('branch_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(PurchaseRequestResource::collection($requests)->response()->getData(true));
    }

    public function store(StorePurchaseRequestRequest $request): JsonResponse
    {
        $purchaseRequest = $this->purchaseRequestService->create($request->validated(), $request->user());

        return response()->json(['data' => new PurchaseRequestResource($purchaseRequest)], 201);
    }

    public function show(PurchaseRequest $purchaseRequest): JsonResponse
    {
        return response()->json(['data' => new PurchaseRequestResource($purchaseRequest->load(['items.product', 'requestedBy', 'approvedBy']))]);
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $purchaseRequest = $this->purchaseRequestService->update($purchaseRequest, $request->validated());

        return response()->json(['data' => new PurchaseRequestResource($purchaseRequest)]);
    }

    public function submit(PurchaseRequest $purchaseRequest): JsonResponse
    {
        return response()->json(['data' => new PurchaseRequestResource($this->purchaseRequestService->submit($purchaseRequest))]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        return response()->json(['data' => new PurchaseRequestResource($this->purchaseRequestService->approve($purchaseRequest, $request->user()))]);
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        return response()->json(['data' => new PurchaseRequestResource($this->purchaseRequestService->reject($purchaseRequest, $request->user()))]);
    }

    public function cancel(PurchaseRequest $purchaseRequest): JsonResponse
    {
        return response()->json(['data' => new PurchaseRequestResource($this->purchaseRequestService->cancel($purchaseRequest))]);
    }

    /** Purchase Request -> Purchase Order: the workflow's second step. */
    public function convertToOrder(ConvertPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $order = $this->purchaseOrderService->createFromRequest(
            $purchaseRequest,
            $request->validated('supplier_id'),
            $request->validated('unit_prices'),
            $request->user(),
            $request->validated('expected_date'),
            $request->validated('notes'),
        );

        return response()->json(['data' => new PurchaseOrderResource($order)], 201);
    }
}
