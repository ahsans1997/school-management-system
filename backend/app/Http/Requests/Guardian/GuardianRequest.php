<?php

namespace App\Http\Requests\Guardian;

use Illuminate\Foundation\Http\FormRequest;

/** Guardian profile fields, shared by create and update. */
abstract class GuardianRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // Digits with optional leading +, spaces or dashes: "01712-345678", "+880 1712 345678".
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 \-]{5,18}$/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number (digits, optionally with +, spaces or dashes).',
        ];
    }
}
