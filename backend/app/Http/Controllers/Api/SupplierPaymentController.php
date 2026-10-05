<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierPayment\StoreSupplierPaymentRequest;
use App\Http\Resources\SupplierPaymentResource;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payments post atomically on creation (see SupplierPaymentService::record())
 * and the ledger is append-only from there — no update/destroy route here,
 * matching StockMovement/JournalEntry.
 */
class SupplierPaymentController extends Controller
{
    public function __construct(private readonly SupplierPaymentService $supplierPaymentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $payments = SupplierPayment::query()
            ->with(['supplier', 'allocations'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->query('supplier_id')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(SupplierPaymentResource::collection($payments)->response()->getData(true));
    }

    public function store(StoreSupplierPaymentRequest $request): JsonResponse
    {
        $payment = $this->supplierPaymentService->record($request->validated(), $request->user());

        return response()->json(['data' => new SupplierPaymentResource($payment)], 201);
    }

    public function show(SupplierPayment $supplierPayment): JsonResponse
    {
        return response()->json(['data' => new SupplierPaymentResource($supplierPayment->load('allocations'))]);
    }
}
