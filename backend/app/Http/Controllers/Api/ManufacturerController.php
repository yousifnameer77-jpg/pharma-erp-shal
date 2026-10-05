<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manufacturer\StoreManufacturerRequest;
use App\Http\Requests\Manufacturer\UpdateManufacturerRequest;
use App\Http\Resources\ManufacturerResource;
use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $manufacturers = Manufacturer::query()
            ->withCount('products')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'ilike', '%'.$request->query('search').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return response()->json(ManufacturerResource::collection($manufacturers)->response()->getData(true));
    }

    public function store(StoreManufacturerRequest $request): JsonResponse
    {
        $manufacturer = Manufacturer::create($request->validated());

        return response()->json(['data' => new ManufacturerResource($manufacturer)], 201);
    }

    public function show(Manufacturer $manufacturer): JsonResponse
    {
        return response()->json(['data' => new ManufacturerResource($manufacturer->loadCount('products'))]);
    }

    public function update(UpdateManufacturerRequest $request, Manufacturer $manufacturer): JsonResponse
    {
        $manufacturer->update($request->validated());

        return response()->json(['data' => new ManufacturerResource($manufacturer->fresh())]);
    }

    public function destroy(Manufacturer $manufacturer): JsonResponse
    {
        $manufacturer->update(['is_active' => false]);

        return response()->json(['message' => 'Manufacturer deactivated successfully.']);
    }
}
