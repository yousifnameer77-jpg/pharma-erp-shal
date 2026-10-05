<?php

namespace App\Http\Requests\Manufacturer;

use Illuminate\Foundation\Http\FormRequest;

class StoreManufacturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'max:80'],
            'contact_info' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
