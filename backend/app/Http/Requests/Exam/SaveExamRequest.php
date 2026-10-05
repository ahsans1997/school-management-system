<?php

namespace App\Http\Requests\Exam;

use App\Enums\ExamType;
use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An exam header. The year is chosen at creation and fixed afterwards;
 * SaveExam checks the dates against it.
 */
class SaveExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // exam.create / exam.edit by middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Exam|null $exam */
        $exam = $this->route('exam');
        $yearId = $exam->academic_year_id ?? $this->integer('academic_year_id');

        return [
            'academic_year_id' => [$exam ? 'prohibited' : 'required', 'integer', Rule::exists('academic_years', 'id')],
            // Unique among archived exams too (matches the DB index).
            'name' => ['required', 'string', 'max:100', Rule::unique('exams', 'name')->where('academic_year_id', $yearId)->ignore($exam)],
            'type' => ['required', Rule::enum(ExamType::class)],
            'grade_scale_id' => ['required', 'integer', Rule::exists('grade_scales', 'id')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This year already has an exam with this name (it may be archived).',
            'academic_year_id.prohibited' => "An exam's academic year can't be changed.",
        ];
    }
}
