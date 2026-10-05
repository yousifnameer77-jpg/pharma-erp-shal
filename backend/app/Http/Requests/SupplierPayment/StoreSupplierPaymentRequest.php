<?php

namespace App\Http\Requests\SupplierPayment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `allocations` is optional — omit it to auto-allocate FIFO across the
 * supplier's oldest outstanding invoices (see
 * SupplierPaymentService::autoAllocate()); pass it to apply the payment to
 * specific invoices/amounts instead.
 */
class StoreSupplierPaymentRequest extends FormRequest
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
            'payment_date' => ['required', 'date'],
            'currency' => ['nullable', 'in:IQD,USD'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer,cheque,card,other'],
            'paid_from_account_id' => ['required', 'uuid', 'exists:chart_of_accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'allocations' => ['sometimes', 'array', 'min:1'],
            'allocations.*.purchase_invoice_id' => ['required_with:allocations', 'uuid', 'exists:purchase_invoices,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'gt:0'],
        ];
    }
}
