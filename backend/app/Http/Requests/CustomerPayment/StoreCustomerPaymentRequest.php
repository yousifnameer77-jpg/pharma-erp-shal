<?php

namespace App\Http\Requests\CustomerPayment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `allocations` is optional — omit it to auto-allocate FIFO across the
 * customer's oldest outstanding invoices (see
 * CustomerPaymentService::autoAllocate()); pass it to apply the payment to
 * specific invoices/amounts instead.
 */
class StoreCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'payment_date' => ['required', 'date'],
            'currency' => ['nullable', 'in:IQD,USD'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,bank_transfer,cheque,card,other'],
            'received_into_account_id' => ['required', 'uuid', 'exists:chart_of_accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'allocations' => ['sometimes', 'array', 'min:1'],
            'allocations.*.sales_invoice_id' => ['required_with:allocations', 'uuid', 'exists:sales_invoices,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'gt:0'],
        ];
    }
}
