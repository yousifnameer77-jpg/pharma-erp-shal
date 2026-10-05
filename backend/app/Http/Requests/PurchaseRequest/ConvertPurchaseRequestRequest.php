<?php

namespace App\Http\Requests\PurchaseRequest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Turns an approved PurchaseRequest into a draft PurchaseOrder for one
 * supplier — `unit_prices` supplies the price for every product on the
 * request (keyed by product_id), since a request carries quantities only.
 */
class ConvertPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'unit_prices' => ['required', 'array', 'min:1'],
            'unit_prices.*' => ['required', 'numeric', 'min:0'],
        ];
    }
}
