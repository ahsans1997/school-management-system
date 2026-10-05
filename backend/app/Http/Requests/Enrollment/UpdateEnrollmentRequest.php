<?php

namespace App\Http\Requests\Enrollment;

use App\Enums\EnrollmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Move section, change roll, or change status (transfer out, left,
 * graduated, back to active). Student and year are fixed.
 */
class UpdateEnrollmentRequest extends FormRequest
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
            // Blank → keep the roll (or take the next one when moving section).
            'roll' => ['nullable', 'integer', 'between:1,9999'],
            'status' => ['required', Rule::enum(EnrollmentStatus::class)],
            // For an ending status; defaults to today.
            'ended_on' => ['nullable', 'date'],
            'exit_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
