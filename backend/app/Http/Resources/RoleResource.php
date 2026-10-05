<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_system_role' => $this->is_system_role,
            'permissions' => $this->whenLoaded(
                'permissions',
                fn () => PermissionResource::collection($this->permissions)
            ),
            'created_at' => $this->created_at,
        ];
    }
}
