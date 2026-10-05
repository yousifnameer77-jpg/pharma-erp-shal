<?php

namespace App\Http\Requests\StockMovement;

use Illuminate\Foundation\Http\FormRequest;

class SupplierReturnStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'uuid', 'exists:batches,id'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reference_type' => ['nullable', 'string', 'max:30'],
            'reference_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
