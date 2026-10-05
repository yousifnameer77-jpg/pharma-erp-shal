<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            if (! empty($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            return $role->load('permissions');
        });
    }

    public function update(Role $role, array $data): Role
    {
        $this->guardSystemRole($role);

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'description' => $data['description'] ?? $role->description,
        ]);

        if (array_key_exists('permission_ids', $data)) {
            $role->permissions()->sync($data['permission_ids']);
        }

        return $role->load('permissions');
    }

    public function delete(Role $role): void
    {
        $this->guardSystemRole($role);
        $role->delete();
    }

    private function guardSystemRole(Role $role): void
    {
        if ($role->is_system_role) {
            throw ValidationException::withMessages([
                'name' => ['Built-in system roles cannot be modified or deleted.'],
            ]);
        }
    }
}
