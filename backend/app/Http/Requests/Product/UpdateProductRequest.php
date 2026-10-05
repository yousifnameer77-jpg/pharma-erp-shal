<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'manufacturer_id' => ['sometimes', 'uuid', 'exists:manufacturers,id'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('products', 'code')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('products', 'barcode')->ignore($productId)],
            'name' => ['sometimes', 'string', 'max:200'],
            'generic_name' => ['nullable', 'string', 'max:200'],
            'form' => ['nullable', 'string', 'max:50'],
            'strength' => ['nullable', 'string', 'max:50'],
            'base_unit' => ['sometimes', 'string', 'max:20'],
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
