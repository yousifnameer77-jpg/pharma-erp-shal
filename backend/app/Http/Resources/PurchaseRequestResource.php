<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
            'request_number' => $this->request_number,
            'status' => $this->status,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->relationLoaded('product') && $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                ] : null,
                'quantity' => $item->quantity,
                'notes' => $item->notes,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
