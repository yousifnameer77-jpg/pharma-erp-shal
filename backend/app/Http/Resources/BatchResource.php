<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'batch_number' => $this->batch_number,
            'manufacture_date' => $this->manufacture_date,
            'expiry_date' => $this->expiry_date,
            'supplier_id' => $this->supplier_id,
            'purchase_price' => $this->purchase_price,
            'is_expired' => $this->is_expired,
            'total_quantity' => $this->total_quantity,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'base_unit' => $this->product->base_unit,
            ] : null),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),
            'stock' => $this->whenLoaded('stock', fn () => $this->stock->map(fn ($s) => [
                'warehouse_id' => $s->warehouse_id,
                'quantity_on_hand' => $s->quantity_on_hand,
                'reserved_quantity' => $s->reserved_quantity,
                'available_quantity' => $s->available_quantity,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
