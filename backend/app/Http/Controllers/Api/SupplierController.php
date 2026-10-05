<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(private readonly SupplierAccountService $supplierAccountService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'ilike', '%'.$request->query('search').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return response()->json(SupplierResource::collection($suppliers)->response()->getData(true));
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        return response()->json(['data' => new SupplierResource($supplier)], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => new SupplierResource($supplier)]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json(['data' => new SupplierResource($supplier->fresh())]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->update(['is_active' => false]);

        return response()->json(['message' => 'Supplier deactivated successfully.']);
    }

    /**
     * "Supplier Balance" — the last step of the Purchasing workflow:
     * total invoiced (posted+ invoices) minus total paid, computed on demand
     * so it can never drift from the invoices/payments behind it.
     */
    public function balance(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => $this->supplierAccountService->balance($supplier)]);
    }
}
