<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerPayment\StoreCustomerPaymentRequest;
use App\Http\Resources\CustomerPaymentResource;
use App\Models\CustomerPayment;
use App\Services\CustomerPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payments post atomically on creation (see CustomerPaymentService::record())
 * and the ledger is append-only from there — no update/destroy route here,
 * matching SupplierPayment/StockMovement/JournalEntry.
 */
class CustomerPaymentController extends Controller
{
    public function __construct(private readonly CustomerPaymentService $customerPaymentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $payments = CustomerPayment::query()
            ->with(['customer', 'allocations'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->query('customer_id')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(CustomerPaymentResource::collection($payments)->response()->getData(true));
    }

    public function store(StoreCustomerPaymentRequest $request): JsonResponse
    {
        $payment = $this->customerPaymentService->record($request->validated(), $request->user());

        return response()->json(['data' => new CustomerPaymentResource($payment)], 201);
    }

    public function show(CustomerPayment $customerPayment): JsonResponse
    {
        return response()->json(['data' => new CustomerPaymentResource($customerPayment->load('allocations'))]);
    }
}
