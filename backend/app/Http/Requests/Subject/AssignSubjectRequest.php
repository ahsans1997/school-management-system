<?php

namespace App\Http\Requests\Subject;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSubjectRequest extends FormRequest
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
            'subject_id' => [
                'bail', 'required', 'integer',
                Rule::exists('subjects', 'id'),
                // The class comes from the route, never the body.
                Rule::unique('class_subjects', 'subject_id')
                    ->where('class_id', $this->route('school_class')->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.exists' => 'That subject does not exist.',
            'subject_id.unique' => 'This subject is already assigned to the class.',
        ];
    }
}
