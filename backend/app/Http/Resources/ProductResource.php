<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'manufacturer_id' => $this->manufacturer_id,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'generic_name' => $this->generic_name,
            'form' => $this->form,
            'strength' => $this->strength,
            'base_unit' => $this->base_unit,
            'pack_size' => $this->pack_size,
            'is_controlled_substance' => $this->is_controlled_substance,
            'requires_prescription' => $this->requires_prescription,
            'min_stock_level' => $this->min_stock_level,
            'reorder_point' => $this->reorder_point,
            'purchase_price' => $this->purchase_price,
            'sale_price' => $this->sale_price,
            'tax_rate' => $this->tax_rate,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'manufacturer' => $this->whenLoaded('manufacturer', fn () => $this->manufacturer ? [
                'id' => $this->manufacturer->id,
                'name' => $this->manufacturer->name,
            ] : null),
            'barcodes' => $this->whenLoaded('barcodes', fn () => $this->barcodes->map(fn ($b) => [
                'id' => $b->id,
                'barcode' => $b->barcode,
                'packaging_unit' => $b->packaging_unit,
                'conversion_factor' => $b->conversion_factor,
            ])),
            // total_stock reads a preloaded `total_stock_sum` aggregate (via
            // withSum('stockRecords', 'quantity_on_hand') in the controller)
            // when present, falling back to a live query otherwise — either
            // way it's always accurate, just cheaper when preloaded.
            'total_stock' => $this->total_stock,
            'is_below_min_stock' => $this->is_below_min_stock,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
