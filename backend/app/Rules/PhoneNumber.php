<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Digits with an optional leading +, spaces or dashes: "01712-345678", "+880 1712 345678". */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\+?[0-9][0-9 \-]{5,18}$/', $value)) {
            $fail('Enter a valid phone number (digits, optionally with +, spaces or dashes).');
        }
    }
}
