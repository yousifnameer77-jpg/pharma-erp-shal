<?php

namespace App\Http\Requests\ChartOfAccount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('chart_of_accounts', 'code')->where(fn ($q) => $q->where('company_id', $this->input('company_id'))),
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'category' => ['nullable', 'in:cash,bank,receivable,payable'],
            'parent_id' => ['nullable', 'uuid', 'exists:chart_of_accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
