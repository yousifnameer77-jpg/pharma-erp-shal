<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'warehouse_id' => $this->warehouse_id,
            'customer_id' => $this->customer_id,
            'sales_invoice_id' => $this->sales_invoice_id,
            'return_number' => $this->return_number,
            'return_date' => $this->return_date,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'sales_invoice_item_id' => $item->sales_invoice_item_id,
                'product_id' => $item->product_id,
                'product' => $item->relationLoaded('product') && $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                ] : null,
                'batch_id' => $item->batch_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
                'line_total' => $item->line_total,
            ])),
            'posted_at' => $this->posted_at,
            'created_at' => $this->created_at,
        ];
    }
}
