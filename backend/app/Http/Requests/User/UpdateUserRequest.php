<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'username' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'nullable', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'full_name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
