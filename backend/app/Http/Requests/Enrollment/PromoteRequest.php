<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Move chosen students of one section/year up into a section of the next class/year. */
class PromoteRequest extends FormRequest
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
            'from_year_id' => ['bail', 'required', 'integer', Rule::exists('academic_years', 'id')],
            'from_section_id' => ['bail', 'required', 'integer', Rule::exists('sections', 'id')],
            'to_year_id' => ['bail', 'required', 'integer', Rule::exists('academic_years', 'id')],
            'to_section_id' => ['bail', 'required', 'integer', Rule::exists('sections', 'id')],
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['integer', 'distinct'],
        ];
    }
}
