<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Names a mark sheet: a paper of the route's exam and a section that sits
 * it. MarksService::resolve checks they fit together and the teacher's
 * subject scope.
 */
class MarkSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission by middleware; scope in MarksService::resolve.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'exam_subject_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
        ];
    }
}
