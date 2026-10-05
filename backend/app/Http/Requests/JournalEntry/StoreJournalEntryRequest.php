<?php

namespace App\Http\Requests\JournalEntry;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A manual entry — for adjustments, opening balances and corrections that
 * don't come from Sales/Purchases/Payments/Expenses, which all post their
 * own entries automatically. Exactly one of `debit`/`credit` must be set per
 * line (JournalEntryService::post() and the DB CHECK constraint both enforce
 * it); the lines don't need to already balance in the request — the service
 * validates that too, before writing anything.
 */
class StoreJournalEntryRequest extends FormRequest
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
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'uuid', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['required_without:lines.*.credit', 'nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['required_without:lines.*.debit', 'nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
