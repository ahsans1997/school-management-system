<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The class (of the route's exam) a results step applies to. */
class ResultClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // result.* permission by middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')],
        ];
    }
}
