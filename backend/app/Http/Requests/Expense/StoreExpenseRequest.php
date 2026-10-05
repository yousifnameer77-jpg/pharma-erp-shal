<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'expense_date' => ['required', 'date'],
            'paid_from_account_id' => [
                'required', 'uuid',
                Rule::exists('chart_of_accounts', 'id')->where('type', 'asset'),
            ],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.account_id' => [
                'required', 'uuid',
                Rule::exists('chart_of_accounts', 'id')->where('type', 'expense'),
            ],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_from_account_id.exists' => 'The selected account must be an existing asset (cash/bank) account.',
            'items.*.account_id.exists' => 'Each line must reference an existing expense-type account.',
        ];
    }
}
