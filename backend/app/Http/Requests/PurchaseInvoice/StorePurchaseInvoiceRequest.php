<?php

namespace App\Http\Requests\PurchaseInvoice;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'uuid', 'exists:purchase_orders,id'],
            'goods_receipt_id' => ['nullable', 'uuid', 'exists:goods_receipts,id'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:50'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
