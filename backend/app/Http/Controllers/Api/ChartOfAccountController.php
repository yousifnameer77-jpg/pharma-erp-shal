<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChartOfAccount\StoreChartOfAccountRequest;
use App\Http\Requests\ChartOfAccount\UpdateChartOfAccountRequest;
use App\Http\Resources\ChartOfAccountResource;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The default chart (Inventory, GRNI, Accounts Payable, Purchase Tax Input,
 * Cash on Hand) is seeded per company by AccountingSeeder/`seedForCompany()`
 * — this controller is for adding to it (e.g. a second bank account).
 */
class ChartOfAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = ChartOfAccount::query()
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->query('company_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('code')
            ->get();

        return response()->json(['data' => ChartOfAccountResource::collection($accounts)]);
    }

    public function store(StoreChartOfAccountRequest $request): JsonResponse
    {
        $account = ChartOfAccount::create($request->validated());

        return response()->json(['data' => new ChartOfAccountResource($account)], 201);
    }

    public function show(ChartOfAccount $chartOfAccount): JsonResponse
    {
        return response()->json(['data' => new ChartOfAccountResource($chartOfAccount)]);
    }

    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $chartOfAccount): JsonResponse
    {
        $chartOfAccount->update($request->validated());

        return response()->json(['data' => new ChartOfAccountResource($chartOfAccount->fresh())]);
    }
}
