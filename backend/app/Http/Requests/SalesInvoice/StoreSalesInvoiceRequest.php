<?php

namespace App\Http\Requests\SalesInvoice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `items.*.batch_id` is optional — omit it to let posting FEFO-allocate
 * across every non-expired batch of that product (splitting across batches
 * if needed); set it to sell that exact batch, still blocked if it's
 * expired. Both behave exactly like Inventory's own `POST /stock-movements/sale`.
 */
class StoreSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.batch_id' => ['nullable', 'uuid', 'exists:batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
