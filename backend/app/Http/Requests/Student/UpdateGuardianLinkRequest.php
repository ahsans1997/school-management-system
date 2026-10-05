<?php

namespace App\Http\Requests\Student;

use App\Enums\GuardianRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuardianLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /**
     * `is_primary: true` makes this guardian the primary one. There is no
     * "un-primary": a student with guardians always has exactly one.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
