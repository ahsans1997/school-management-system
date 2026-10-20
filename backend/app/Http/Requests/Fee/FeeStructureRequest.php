<?php

namespace App\Http\Requests\Fee;

use App\Enums\FeeFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A class's fee for a year. On update only the amount, frequency and
 * active flag change; the fee type, class and year are fixed.
 */
class FeeStructureRequest extends FormRequest
{
    /** A money amount: plain digits with up to 2 decimals ("1500", "1500.5"), at most 13 digits. */
    public const MONEY = ['numeric', 'regex:/^\d{1,13}(\.\d{1,2})?$/'];

    public function authorize(): bool
    {
        return true; // fee.create by middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $values = [
            // Money: up to 13 digits and 2 decimals, above zero (spec §53).
            'amount' => ['required', ...self::MONEY, 'gt:0'],
            'frequency' => ['required', Rule::enum(FeeFrequency::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
        if ($this->route('fee_structure') !== null) {
            return $values;
        }

        return [
            'fee_type_id' => ['bail', 'required', 'integer', Rule::exists('fee_types', 'id'),
                Rule::unique('fee_structures')->where('class_id', $this->integer('class_id'))->where('academic_year_id', $this->integer('academic_year_id'))],
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')],
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            ...$values,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fee_type_id.unique' => 'This fee is already set for the class in that year; edit it instead.',
            'amount.gt' => 'The amount must be more than zero.',
            'amount.regex' => 'Enter the amount as a number with up to 2 decimals, e.g. 1500 or 1500.50.',
        ];
    }
}
