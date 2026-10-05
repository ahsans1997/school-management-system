<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A grade scale and its full set of bands (send a band's id to keep it). */
class SaveGradeScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // exam.grading by middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('grade_scales', 'name')->ignore($this->route('grade_scale'))],
            'grades' => ['required', 'array', 'min:1', 'max:20'],
            'grades.*.id' => ['nullable', 'integer'],
            'grades.*.name' => ['required', 'string', 'max:10'],
            'grades.*.grade_point' => ['required', 'numeric', 'between:0,10', 'decimal:0,2'],
            'grades.*.min_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A grade scale with this name already exists.',
        ];
    }
}
