<?php

namespace App\Http\Requests\Fee;

use App\Enums\FeeCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or update a fee type (the type being updated comes from the route). */
class FeeTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // fee.create by middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('fee_type');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('fee_types', 'name')->ignore($current)],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('fee_types', 'code')->ignore($current)],
            'category' => ['required', Rule::enum(FeeCategory::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
