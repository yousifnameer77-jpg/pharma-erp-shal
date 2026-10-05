<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
            'customer_id' => $this->customer_id,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_due' => $this->remaining_due,
            'notes' => $this->notes,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->relationLoaded('product') && $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                ] : null,
                'batch_id' => $item->batch_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
                'discount_rate' => $item->discount_rate,
                'line_total' => $item->line_total,
                'returned_quantity' => $item->returned_quantity,
                'remaining_returnable_quantity' => $item->remaining_returnable_quantity,
                // The batches FEFO actually drew from for this line, once posted.
                'stock_movements' => $item->relationLoaded('stockMovements') ? $item->stockMovements->map(fn ($m) => [
                    'batch_id' => $m->batch_id,
                    'quantity' => $m->quantity,
                ]) : null,
            ])),
            'posted_at' => $this->posted_at,
            'created_at' => $this->created_at,
        ];
    }
}
