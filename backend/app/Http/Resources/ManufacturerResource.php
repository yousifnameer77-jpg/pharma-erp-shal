<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManufacturerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country' => $this->country,
            'contact_info' => $this->contact_info,
            'is_active' => $this->is_active,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
