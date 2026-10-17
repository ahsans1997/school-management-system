<?php

namespace App\Http\Requests\Student;

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Models\Student;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /** The student being updated, or null when creating. */
    abstract protected function student(): ?Student;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::fieldRules($this->student());
    }

    /**
     * The student fields' rules, shared with the CSV/Excel import.
     *
     * @return array<string, mixed>
     */
    public static function fieldRules(?Student $student): array
    {
        $creating = $student === null;

        // Generated when left blank on create (or typed in, e.g. when migrating
        // existing records). Unique across archived students too.
        $identifier = fn (string $column) => [
            $creating ? 'nullable' : 'required', 'string', 'max:20',
            'regex:/^[A-Za-z0-9][A-Za-z0-9\/-]*$/',
            Rule::unique('students', $column)->ignore($student),
        ];

        return [
            'student_id' => $identifier('student_id'),
            'admission_no' => $identifier('admission_no'),
            'admission_date' => [$creating ? 'nullable' : 'required', 'date'],
            'name' => ['required', 'string', 'max:100'],
            'dob' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'blood_group' => ['nullable', Rule::enum(BloodGroup::class)],
            'father_name' => ['nullable', 'string', 'max:100'],
            'mother_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20', new PhoneNumber],
            'address' => ['nullable', 'string', 'max:500'],
            'previous_school' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::fieldMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function fieldMessages(): array
    {
        return [
            'student_id.regex' => 'Use letters, digits, dashes or slashes.',
            'admission_no.regex' => 'Use letters, digits, dashes or slashes.',
            'dob.before' => 'The date of birth must be in the past.',
        ];
    }
}
