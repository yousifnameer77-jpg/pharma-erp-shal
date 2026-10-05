<?php

namespace App\Http\Requests\StockMovement;

use Illuminate\Foundation\Http\FormRequest;

class DamageStockRequest extends FormRequest
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
            // Required: a write-off always needs a reason on the record for audit.
            'notes' => ['required', 'string', 'max:500'],
        ];
    }
}
