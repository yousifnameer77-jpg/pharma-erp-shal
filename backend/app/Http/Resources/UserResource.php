<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            // Every company-scoped list/report endpoint takes a `company_id`
            // filter, so the frontend needs this to build those requests —
            // derived from the loaded branch rather than stored on User
            // directly, since a user only ever belongs to one company via
            // their branch.
            'company_id' => $this->whenLoaded('branch', fn () => $this->branch?->company_id),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                // The user_roles pivot row's own id — DELETE
                // /users/{user}/roles/{userRoleId} (UserController::revokeRole)
                // needs this, not the role id, since the same role can be
                // assigned to a user more than once at different scopes.
                'user_role_id' => $role->pivot->id,
                'name' => $role->name,
                'scope' => [
                    'branch_id' => $role->pivot->branch_id,
                    'warehouse_id' => $role->pivot->warehouse_id,
                ],
                'permissions' => $role->relationLoaded('permissions')
                    ? $role->permissions->pluck('code')
                    : null,
            ])),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
