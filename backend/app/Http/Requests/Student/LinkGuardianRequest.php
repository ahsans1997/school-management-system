<?php

namespace App\Http\Requests\Student;

use App\Enums\GuardianRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkGuardianRequest extends FormRequest
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
            'guardian_id' => [
                'bail', 'required', 'integer',
                Rule::exists('guardians', 'id'),
                // The student comes from the route, never the body.
                Rule::unique('student_guardians', 'guardian_id')
                    ->where('student_id', $this->route('student')->id),
            ],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guardian_id.exists' => 'That guardian does not exist.',
            'guardian_id.unique' => 'This guardian is already linked to the student.',
        ];
    }
}
