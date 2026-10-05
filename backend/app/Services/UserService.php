<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $data['password'] = Hash::make($data['password']);
            unset($data['password_confirmation']);

            return User::create($data);
        });
    }

    public function update(User $user, array $data): User
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        unset($data['password_confirmation']);

        $user->update($data);

        return $user->fresh();
    }

    /**
     * Users are deactivated, never hard-deleted: they're referenced by
     * audit_logs, stock_movements, journal_entries and similar history tables
     * that must never lose their author.
     */
    public function deactivate(User $user): void
    {
        $user->update(['is_active' => false]);
        $user->tokens()->delete();
    }

    public function assignRole(User $user, string $roleId, ?string $branchId = null, ?string $warehouseId = null): UserRole
    {
        return UserRole::firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
        ]);
    }

    public function revokeRole(User $user, string $userRoleId): void
    {
        $user->userRoles()->where('id', $userRoleId)->delete();
    }
}
