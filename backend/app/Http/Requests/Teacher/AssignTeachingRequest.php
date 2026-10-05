<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Teach {subject_id} in {section_id}. The class-subject row is resolved server-side. */
class AssignTeachingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_id' => ['bail', 'required', 'integer', Rule::exists('sections', 'id')],
            'subject_id' => ['bail', 'required', 'integer', Rule::exists('subjects', 'id')],
        ];
    }
}
