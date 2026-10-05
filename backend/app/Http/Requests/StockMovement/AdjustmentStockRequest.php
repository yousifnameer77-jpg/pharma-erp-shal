<?php

namespace App\Http\Requests\StockMovement;

use Illuminate\Foundation\Http\FormRequest;

class AdjustmentStockRequest extends FormRequest
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
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            // Required: an adjustment is a correction to the count, so the
            // record must say why (physical count, system error, etc).
            'notes' => ['required', 'string', 'max:500'],
        ];
    }
}
