<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoodsReceipt\StoreGoodsReceiptRequest;
use App\Http\Resources\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Services\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function __construct(private readonly GoodsReceiptService $goodsReceiptService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $receipts = GoodsReceipt::query()
            ->with(['purchaseOrder.supplier'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('purchase_order_id'), fn ($q) => $q->where('purchase_order_id', $request->query('purchase_order_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->query('warehouse_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(GoodsReceiptResource::collection($receipts)->response()->getData(true));
    }

    /** Records what arrived — no stock/accounting effect until post(). */
    public function store(StoreGoodsReceiptRequest $request): JsonResponse
    {
        $receipt = $this->goodsReceiptService->create($request->validated(), $request->user());

        return response()->json(['data' => new GoodsReceiptResource($receipt)], 201);
    }

    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        return response()->json(['data' => new GoodsReceiptResource($goodsReceipt->load(['purchaseOrder.supplier', 'items.product']))]);
    }

    /**
     * Posts the receipt: creates/tops-up batches and stock via
     * BatchService::receive() (Inventory), advances the purchase order's
     * received_quantity/status, and posts a Dr Inventory / Cr GRNI journal
     * entry (Accounting). Irreversible — correct mistakes with a
     * compensating Inventory movement, not by editing this receipt.
     */
    public function post(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $receipt = $this->goodsReceiptService->post($goodsReceipt, $request->user());

        return response()->json(['data' => new GoodsReceiptResource($receipt)]);
    }
}
