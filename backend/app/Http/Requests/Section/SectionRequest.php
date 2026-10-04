<?php

namespace App\Http\Requests\Section;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /** The class the section belongs to — from the route, never the body. */
    abstract protected function classId(): int;

    /** The section being updated (excluded from uniqueness), or null. */
    abstract protected function ignoredSection(): ?Section;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('sections', 'name')
                    ->where('class_id', $this->classId())
                    ->ignore($this->ignoredSection()),
            ],
            'capacity' => ['required', 'integer', 'between:1,1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This class already has a section with that name.',
        ];
    }
}
