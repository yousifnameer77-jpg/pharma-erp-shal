<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->with('branch')
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->query('branch_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => WarehouseResource::collection($warehouses)]);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());

        return response()->json(['data' => new WarehouseResource($warehouse->load('branch'))], 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return response()->json(['data' => new WarehouseResource($warehouse->load('branch'))]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());

        return response()->json(['data' => new WarehouseResource($warehouse->fresh('branch'))]);
    }

    // No destroy(): stock/batches/stock_movements all reference a warehouse,
    // so deleting one is never safe once it's been used. Deactivate it
    // instead (`PUT .../{id}` with `is_active: false`).
}
