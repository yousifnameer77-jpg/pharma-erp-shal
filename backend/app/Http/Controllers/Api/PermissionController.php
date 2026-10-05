<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Read-only: the permission catalogue is seeded (PermissionSeeder), not
     * managed through the API, so new permissions ship as code + a migration
     * of their own when a new module is added.
     */
    public function index(Request $request): JsonResponse
    {
        $permissions = Permission::query()
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->query('module')))
            ->orderBy('module')
            ->orderBy('code')
            ->get()
            ->groupBy('module');

        return response()->json([
            'data' => $permissions->map(fn ($group) => PermissionResource::collection($group)),
        ]);
    }
}
