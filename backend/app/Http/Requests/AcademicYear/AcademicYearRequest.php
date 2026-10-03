<?php

namespace App\Http\Requests\AcademicYear;

use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for creating/updating a year. `is_current` is deliberately not
 * accepted here — the only way to change it is POST /set-current.
 */
abstract class AcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /** The year being updated (excluded from uniqueness/overlap), or null. */
    abstract protected function ignoredYear(): ?AcademicYear;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('academic_years', 'name')->ignore($this->ignoredYear()),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /**
     * Years may not overlap, so every date belongs to at most one year.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['start_date', 'end_date'])) {
                    return;
                }

                $overlapping = AcademicYear::query()
                    ->when($this->ignoredYear(), fn ($q, AcademicYear $year) => $q->whereKeyNot($year->getKey()))
                    ->where('start_date', '<=', $this->date('end_date'))
                    ->where('end_date', '>=', $this->date('start_date'))
                    ->value('name');

                if ($overlapping) {
                    $validator->errors()->add(
                        'start_date',
                        "These dates overlap the {$overlapping} academic year.",
                    );
                }
            },
        ];
    }
}
