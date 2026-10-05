<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->input('branch_id', $this->route('warehouse')?->branch_id);

        return [
            'branch_id' => ['sometimes', 'uuid', 'exists:branches,id'],
            'name' => ['sometimes', 'string', 'max:150'],
            'code' => [
                'sometimes', 'string', 'max:20',
                Rule::unique('warehouses')
                    ->where(fn ($q) => $q->where('branch_id', $branchId))
                    ->ignore($this->route('warehouse')?->id),
            ],
            'type' => ['sometimes', 'in:main,sub,quarantine,returns'],
            'location' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
