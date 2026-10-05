<?php

namespace App\Http\Requests\ChartOfAccount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `code` and `type` are deliberately not editable here: every posting
 * service looks accounts up by `code` (see ChartOfAccount::findByCode()), so
 * renaming a well-known code out from under it would silently break every
 * future posting. Deactivate and create a replacement instead.
 */
class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'category' => ['nullable', 'in:cash,bank,receivable,payable'],
            'parent_id' => [
                'nullable', 'uuid', 'exists:chart_of_accounts,id',
                Rule::notIn([$this->route('chartOfAccount')?->id]),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
