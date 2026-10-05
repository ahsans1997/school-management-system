<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Every paper one class sits in an exam (the class comes from the route).
 * SaveExamSubjects checks how the rows fit together: subject and section of
 * this class, pass ≤ full, the split adding up, dates inside the exam, no
 * overlapping papers.
 */
class SaveExamSubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // exam.edit by middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $marks = ['nullable', 'numeric', 'min:0', 'max:1000', 'decimal:0,2'];

        return [
            'subjects' => ['present', 'array', 'max:60'],
            'subjects.*.class_subject_id' => ['required', 'integer'],
            'subjects.*.section_id' => ['nullable', 'integer'],
            'subjects.*.full_marks' => ['required', 'numeric', 'gt:0', 'max:1000', 'decimal:0,2'],
            'subjects.*.pass_marks' => ['required', 'numeric', 'min:0', 'max:1000', 'decimal:0,2'],
            'subjects.*.cq_marks' => $marks,
            'subjects.*.mcq_marks' => $marks,
            'subjects.*.practical_marks' => $marks,
            'subjects.*.exam_date' => ['nullable', 'date_format:Y-m-d'],
            'subjects.*.start_time' => ['nullable', 'date_format:H:i', 'required_with:subjects.*.end_time'],
            'subjects.*.end_time' => ['nullable', 'date_format:H:i', 'required_with:subjects.*.start_time'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subjects.*.start_time.required_with' => 'Give both a start and an end time, or neither.',
            'subjects.*.end_time.required_with' => 'Give both a start and an end time, or neither.',
        ];
    }
}
