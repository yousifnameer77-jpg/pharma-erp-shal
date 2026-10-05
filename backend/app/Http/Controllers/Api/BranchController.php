<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;

/**
 * Read-only: branches are seeded per company at setup time (see
 * DatabaseSeeder) rather than created through the API. This just gives the
 * frontend something to list/select from — e.g. the branch picker on the
 * Warehouses page's "new warehouse" form.
 */
class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        $branches = Branch::query()
            ->withCount('warehouses')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => BranchResource::collection($branches)]);
    }

    public function show(Branch $branch): JsonResponse
    {
        return response()->json(['data' => new BranchResource($branch->loadCount('warehouses'))]);
    }
}
