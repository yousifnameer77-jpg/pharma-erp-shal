<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReturn\StoreSalesReturnRequest;
use App\Http\Resources\SalesReturnResource;
use App\Models\SalesReturn;
use App\Services\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReturnController extends Controller
{
    public function __construct(private readonly SalesReturnService $salesReturnService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $returns = SalesReturn::query()
            ->with(['customer'])
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->query('customer_id')))
            ->when($request->filled('sales_invoice_id'), fn ($q) => $q->where('sales_invoice_id', $request->query('sales_invoice_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json(SalesReturnResource::collection($returns)->response()->getData(true));
    }

    public function store(StoreSalesReturnRequest $request): JsonResponse
    {
        $return = $this->salesReturnService->create($request->validated(), $request->user());

        return response()->json(['data' => new SalesReturnResource($return)], 201);
    }

    public function show(SalesReturn $salesReturn): JsonResponse
    {
        return response()->json(['data' => new SalesReturnResource($salesReturn->load(['customer', 'items.product']))]);
    }

    /**
     * Posts the return: puts stock back into the named batch, posts the
     * reversing journal entry, and reduces the customer's balance.
     */
    public function post(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        $return = $this->salesReturnService->post($salesReturn, $request->user());

        return response()->json(['data' => new SalesReturnResource($return)]);
    }

    public function cancel(SalesReturn $salesReturn): JsonResponse
    {
        return response()->json(['data' => new SalesReturnResource($this->salesReturnService->cancel($salesReturn))]);
    }
}
