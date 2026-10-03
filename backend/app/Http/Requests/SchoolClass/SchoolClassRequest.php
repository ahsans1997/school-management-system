<?php

namespace App\Http\Requests\SchoolClass;

use App\Enums\Medium;
use App\Enums\Shift;
use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /** The class being updated (excluded from uniqueness), or null. */
    abstract protected function ignoredClass(): ?SchoolClass;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('classes', 'name')->ignore($this->ignoredClass()),
            ],
            // Class 1 = 1; zero/negatives for pre-primary (e.g. KG = 0).
            'numeric_level' => ['required', 'integer', 'between:-5,20'],
            'shift' => ['nullable', Rule::enum(Shift::class)],
            'medium' => ['nullable', Rule::enum(Medium::class)],
        ];
    }
}
