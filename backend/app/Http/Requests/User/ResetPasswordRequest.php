<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Password optional: when omitted, a temporary one is generated.
        return [
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
