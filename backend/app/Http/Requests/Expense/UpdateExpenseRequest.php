<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'paid_from_account_id' => [
                'sometimes', 'uuid',
                Rule::exists('chart_of_accounts', 'id')->where('type', 'asset'),
            ],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.account_id' => [
                'required_with:items', 'uuid',
                Rule::exists('chart_of_accounts', 'id')->where('type', 'expense'),
            ],
            'items.*.amount' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
