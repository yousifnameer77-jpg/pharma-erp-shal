<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            // A warehouse without its branch is meaningless, so when warehouse_id is
            // given, branch_id must be given too (checked in withValidator below).
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('warehouse_id') && ! $this->filled('branch_id')) {
                $validator->errors()->add('branch_id', 'A branch is required when scoping a role to a warehouse.');
            }
        });
    }
}
