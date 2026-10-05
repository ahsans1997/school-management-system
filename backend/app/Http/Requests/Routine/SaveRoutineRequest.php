<?php

namespace App\Http\Requests\Routine;

use App\Enums\Weekday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A whole week of periods. POST /routines also names the section and year;
 * PUT /routines/{id} sends only the slots. Each slot is either teaching
 * (teaching_assignment_id) or a labelled break.
 */
class SaveRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission by middleware; section scope in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('routine') === null;

        return [
            'section_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('sections', 'id')],
            'academic_year_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('academic_years', 'id')],
            'slots' => ['present', 'array', 'max:120'],
            'slots.*.day' => ['required', Rule::enum(Weekday::class)],
            'slots.*.period_no' => ['required', 'integer', 'between:1,20'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i'],
            'slots.*.teaching_assignment_id' => ['nullable', 'integer', 'required_without:slots.*.label'],
            'slots.*.label' => ['nullable', 'string', 'max:50', 'required_without:slots.*.teaching_assignment_id'],
            'slots.*.room' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slots.*.teaching_assignment_id.required_without' => 'Choose a subject, or label the period (e.g. Break).',
            'slots.*.label.required_without' => 'Choose a subject, or label the period (e.g. Break).',
        ];
    }
}
