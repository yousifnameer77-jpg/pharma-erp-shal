<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($roleId)],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['uuid', 'exists:permissions,id'],
        ];
    }
}
