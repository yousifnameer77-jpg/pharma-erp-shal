<?php

namespace App\Http\Requests\StockMovement;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Either `batch_id` (sell this exact batch) or `product_id` (auto-allocate
 * FEFO across every non-expired batch of this product in the warehouse) is
 * required — see StockMovementService::recordSale().
 */
class SellStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required_without:batch_id', 'uuid', 'exists:products,id'],
            'batch_id' => ['required_without:product_id', 'nullable', 'uuid', 'exists:batches,id'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reference_type' => ['nullable', 'string', 'max:30'],
            'reference_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
