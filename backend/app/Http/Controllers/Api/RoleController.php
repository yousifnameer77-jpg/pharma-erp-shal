<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => RoleResource::collection(Role::with('permissions')->orderBy('name')->get()),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->create($request->validated());

        return response()->json(['data' => new RoleResource($role)], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(['data' => new RoleResource($role->load('permissions'))]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->update($role, $request->validated());

        return response()->json(['data' => new RoleResource($role)]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->delete($role);

        return response()->json(['message' => 'Role deleted successfully.']);
    }
}
