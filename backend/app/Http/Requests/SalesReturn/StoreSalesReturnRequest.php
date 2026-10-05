<?php

namespace App\Http\Requests\SalesReturn;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'sales_invoice_id' => ['nullable', 'uuid', 'exists:sales_invoices,id'],
            'return_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => ['nullable', 'uuid', 'exists:sales_invoice_items,id'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.batch_id' => ['required', 'uuid', 'exists:batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
