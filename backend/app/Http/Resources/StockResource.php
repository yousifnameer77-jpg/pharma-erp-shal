<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'warehouse_id' => $this->warehouse_id,
            'batch_id' => $this->batch_id,
            'quantity_on_hand' => $this->quantity_on_hand,
            'reserved_quantity' => $this->reserved_quantity,
            'available_quantity' => $this->available_quantity,
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ] : null),
            'batch' => $this->whenLoaded('batch', fn () => $this->batch ? [
                'id' => $this->batch->id,
                'batch_number' => $this->batch->batch_number,
                'expiry_date' => $this->batch->expiry_date,
                'is_expired' => $this->batch->is_expired,
                'product' => $this->batch->relationLoaded('product') && $this->batch->product ? [
                    'id' => $this->batch->product->id,
                    'name' => $this->batch->product->name,
                ] : null,
            ] : null),
            'updated_at' => $this->updated_at,
        ];
    }
}
