<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'batch_id' => $this->batch_id,
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'quantity' => $this->quantity,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'unit_cost' => $this->unit_cost,
            'notes' => $this->notes,
            'performed_by' => $this->performed_by,
            'batch' => $this->whenLoaded('batch', fn () => $this->batch ? [
                'id' => $this->batch->id,
                'batch_number' => $this->batch->batch_number,
                'product' => $this->batch->relationLoaded('product') && $this->batch->product ? [
                    'id' => $this->batch->product->id,
                    'name' => $this->batch->product->name,
                ] : null,
            ] : null),
            'from_warehouse' => $this->whenLoaded('fromWarehouse', fn () => $this->fromWarehouse ? [
                'id' => $this->fromWarehouse->id,
                'name' => $this->fromWarehouse->name,
            ] : null),
            'to_warehouse' => $this->whenLoaded('toWarehouse', fn () => $this->toWarehouse ? [
                'id' => $this->toWarehouse->id,
                'name' => $this->toWarehouse->name,
            ] : null),
            'performed_by_user' => $this->whenLoaded('performedBy', fn () => $this->performedBy ? [
                'id' => $this->performedBy->id,
                'full_name' => $this->performedBy->full_name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
