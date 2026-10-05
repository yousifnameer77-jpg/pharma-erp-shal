<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'supplier_id' => $this->supplier_id,
            'purchase_order_id' => $this->purchase_order_id,
            'goods_receipt_id' => $this->goods_receipt_id,
            'invoice_number' => $this->invoice_number,
            'supplier_invoice_number' => $this->supplier_invoice_number,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_due' => $this->remaining_due,
            'notes' => $this->notes,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->relationLoaded('product') && $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                ] : null,
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
