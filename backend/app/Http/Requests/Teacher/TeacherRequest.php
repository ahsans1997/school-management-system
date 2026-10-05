<?php

namespace App\Http\Requests\Teacher;

use App\Models\Teacher;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TeacherRequest extends FormRequest
{
    /** The teacher being updated, or null when creating. */
    abstract protected function teacher(): ?Teacher;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->teacher() === null;

        return [
            // Generated when left blank on create; unique across archived teachers too.
            'employee_id' => [
                $creating ? 'nullable' : 'required', 'string', 'max:20',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\/-]*$/',
                Rule::unique('teachers', 'employee_id')->ignore($this->teacher()),
            ],
            'name' => ['required', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'joining_date' => ['required', 'date', 'after:1950-01-01'],
            'phone' => ['required', 'string', 'max:20', new PhoneNumber],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            // DECIMAL(15,2): up to 13 digits before the point, 2 after.
            'salary' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.regex' => 'Use letters, digits, dashes or slashes.',
            'salary.decimal' => 'The salary can have at most 2 decimal places.',
        ];
    }
}
