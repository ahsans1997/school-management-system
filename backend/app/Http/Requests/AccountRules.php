<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Validation for a new person login (email + initial password). */
final class AccountRules
{
    /**
     * Standalone (`$prefix = ''`, required) or nested under an optional
     * `account` object (`'account.'`, required only when it's sent).
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = ''): array
    {
        $required = $prefix === '' ? 'required' : 'required_with:'.rtrim($prefix, '.');

        return [
            "{$prefix}email" => [$required, 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            "{$prefix}password" => [$required, 'string', 'min:8'],
        ];
    }
}
