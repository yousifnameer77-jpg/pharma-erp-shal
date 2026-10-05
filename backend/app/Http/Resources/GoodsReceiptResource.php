<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'warehouse_id' => $this->warehouse_id,
            'purchase_order_id' => $this->purchase_order_id,
            'receipt_number' => $this->receipt_number,
            'receipt_date' => $this->receipt_date,
            'status' => $this->status,
            'received_by' => $this->received_by,
            'notes' => $this->notes,
            'posted_at' => $this->posted_at,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => $this->purchaseOrder ? [
                'id' => $this->purchaseOrder->id,
                'order_number' => $this->purchaseOrder->order_number,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'product_id' => $item->product_id,
                'product' => $item->relationLoaded('product') && $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                ] : null,
                'batch_number' => $item->batch_number,
                'manufacture_date' => $item->manufacture_date,
                'expiry_date' => $item->expiry_date,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
