<?php

namespace App\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating a batch and receiving its first quantity are the same API call —
 * see BatchService::receive(). Posting the same product + batch_number again
 * (a second delivery of an existing batch) tops up that batch's stock
 * instead of erroring, as long as the expiry date matches.
 */
class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'batch_number' => ['required', 'string', 'max:50'],
            'manufacture_date' => ['nullable', 'date'],
            'expiry_date' => ['required', 'date', 'after_or_equal:manufacture_date'],
            'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reference_type' => ['nullable', 'string', 'max:30'],
            'reference_id' => ['nullable', 'uuid'],
        ];
    }
}
