<?php

namespace App\Http\Requests\Subject;

use App\Enums\SubjectType;
use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /** The subject being updated (excluded from uniqueness), or null. */
    abstract protected function ignoredSubject(): ?Subject;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('subjects', 'name')->ignore($this->ignoredSubject()),
            ],
            // Blank arrives as null (ConvertEmptyStringsToNull), so it never collides.
            'code' => [
                'nullable', 'string', 'max:20',
                Rule::unique('subjects', 'code')->ignore($this->ignoredSubject()),
            ],
            'type' => ['nullable', Rule::enum(SubjectType::class)],
        ];
    }
}
