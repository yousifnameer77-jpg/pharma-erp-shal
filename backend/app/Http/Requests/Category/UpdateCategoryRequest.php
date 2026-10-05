<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'parent_id' => ['nullable', 'uuid', 'exists:categories,id', Rule::notIn([$categoryId])],
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ];
    }
}
