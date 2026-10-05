<?php

namespace App\Http\Requests\SalesInvoice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'uuid', 'exists:products,id'],
            'items.*.batch_id' => ['nullable', 'uuid', 'exists:batches,id'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
