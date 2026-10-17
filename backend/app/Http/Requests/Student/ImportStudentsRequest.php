<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/** A CSV or Excel file of students; `dry_run` checks it without saving. */
class ImportStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // student.create by middleware; enrollment.create checked per file.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Name and content must both say CSV/text or .xlsx.
            'file' => ['required', 'file', 'max:2048', 'extensions:csv,txt,xlsx', 'mimes:csv,txt,xlsx'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload a CSV or Excel (.xlsx) file.',
            'file.mimes' => 'Upload a CSV or Excel (.xlsx) file.',
            'file.max' => 'The file can be at most 2 MB.',
        ];
    }
}
