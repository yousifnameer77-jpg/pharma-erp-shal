<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'phone' => $this->phone,
            'manager_user_id' => $this->manager_user_id,
            'is_active' => $this->is_active,
            'warehouses_count' => $this->when(isset($this->warehouses_count), $this->warehouses_count),
        ];
    }
}
