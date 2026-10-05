<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Enroll a student into a section for a year. The class comes from the section. */
class StoreEnrollmentRequest extends FormRequest
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
            // Archived students can't be enrolled.
            'student_id' => ['bail', 'required', 'integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
            'academic_year_id' => ['bail', 'required', 'integer', Rule::exists('academic_years', 'id')],
            'section_id' => ['bail', 'required', 'integer', Rule::exists('sections', 'id')],
            // Blank → next roll in the section.
            'roll' => ['nullable', 'integer', 'between:1,9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.exists' => 'That student does not exist or is archived.',
        ];
    }
}
