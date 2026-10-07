<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used to create a category and to edit one of the user's own categories. */
class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category
            ? ($this->user()?->can('update', $category) ?? false)
            : $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['nullable', 'string', 'max:60', 'required_without:name_ta'],
            'name_ta' => ['nullable', 'string', 'max:60', 'required_without:name_en'],
            'emoji' => ['nullable', 'string', 'max:16'],
            'kind' => ['required', Rule::in(Category::USER_KINDS)],
        ];
    }

    public function messages(): array
    {
        return [
            'name_en.required_without' => 'Type the category name in English, Tamil, or both.',
            'name_ta.required_without' => 'Type the category name in English, Tamil, or both.',
            'kind.required' => 'Choose what kind of words go in this category.',
            'kind.in' => 'Choose what kind of words go in this category.',
        ];
    }
}
