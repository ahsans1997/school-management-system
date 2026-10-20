<?php

namespace App\Http\Requests\Fee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Generate a month's invoices for a class or section. Shapes only:
 * GenerateInvoices checks the month against the year and the chosen fees
 * against the class's fee structures. No amounts are accepted.
 */
class GenerateInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // fee.create by middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')],
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')],
            'period' => ['required', 'date_format:Y-m'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'fee_type_ids' => ['nullable', 'array', 'max:50'],
            'fee_type_ids.*' => ['integer', 'distinct'],
        ];
    }
}
