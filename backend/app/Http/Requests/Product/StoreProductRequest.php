<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'manufacturer_id' => ['required', 'uuid', 'exists:manufacturers,id'],
            'code' => ['required', 'string', 'max:50', 'unique:products,code'],
            'barcode' => ['nullable', 'string', 'max:50', 'unique:products,barcode'],
            'name' => ['required', 'string', 'max:200'],
            'generic_name' => ['nullable', 'string', 'max:200'],
            'form' => ['nullable', 'string', 'max:50'],
            'strength' => ['nullable', 'string', 'max:50'],
            'base_unit' => ['required', 'string', 'max:20'],
            'pack_size' => ['nullable', 'integer', 'min:1'],
            'is_controlled_substance' => ['sometimes', 'boolean'],
            'requires_prescription' => ['sometimes', 'boolean'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
