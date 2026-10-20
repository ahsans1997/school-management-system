<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;

/** One student's marks on a paper (the mark comes from the route). */
class UpdateMarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission by middleware; subject scope in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return SaveMarksRequest::valueRules();
    }
}
