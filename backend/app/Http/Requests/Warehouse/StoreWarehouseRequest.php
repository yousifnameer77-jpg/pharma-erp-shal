<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('warehouses')->where(fn ($q) => $q->where('branch_id', $this->input('branch_id'))),
            ],
            'type' => ['required', 'in:main,sub,quarantine,returns'],
            'location' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
