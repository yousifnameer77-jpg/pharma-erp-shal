<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AssignRoleRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with(['branch', 'roles'])
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->query('branch_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($q2) => $q2->where('full_name', 'ilike', $term)->orWhere('username', 'ilike', $term));
            })
            ->orderBy('full_name')
            ->paginate($request->integer('per_page', 20));

        return response()->json(UserResource::collection($users)->response()->getData(true));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return response()->json(['data' => new UserResource($user)], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($user->load(['branch', 'roles.permissions'])),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->update($user, $request->validated());

        return response()->json(['data' => new UserResource($user)]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->deactivate($user);

        return response()->json(['message' => 'User deactivated successfully.']);
    }

    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        $userRole = $this->userService->assignRole(
            $user,
            $request->validated('role_id'),
            $request->validated('branch_id'),
            $request->validated('warehouse_id'),
        );

        return response()->json([
            'data' => $userRole->load(['role', 'branch', 'warehouse']),
        ], 201);
    }

    public function revokeRole(User $user, string $userRoleId): JsonResponse
    {
        $this->userService->revokeRole($user, $userRoleId);

        return response()->json(['message' => 'Role revoked successfully.']);
    }
}
