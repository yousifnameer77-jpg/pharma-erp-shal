<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerAccountService $customerAccountService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'ilike', '%'.$request->query('search').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return response()->json(CustomerResource::collection($customers)->response()->getData(true));
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return response()->json(['data' => new CustomerResource($customer)], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json(['data' => new CustomerResource($customer)]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json(['data' => new CustomerResource($customer->fresh())]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->update(['is_active' => false]);

        return response()->json(['message' => 'Customer deactivated successfully.']);
    }

    /**
     * "Customer Balance" — total invoiced (posted+) minus total returned
     * minus total paid, computed on demand so it can never drift.
     */
    public function balance(Customer $customer): JsonResponse
    {
        return response()->json(['data' => $this->customerAccountService->balance($customer)]);
    }
}
