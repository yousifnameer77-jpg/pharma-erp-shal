<?php

namespace App\Http\Requests\GoodsReceipt;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
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
            'purchase_order_id' => ['required', 'uuid', 'exists:purchase_orders,id'],
            'receipt_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'uuid', 'exists:purchase_order_items,id'],
            'items.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.manufacture_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['required', 'date', 'after_or_equal:items.*.manufacture_date'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ];
    }
}
