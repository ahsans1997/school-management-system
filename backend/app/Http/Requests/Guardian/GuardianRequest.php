<?php

namespace App\Http\Requests\Guardian;

use App\Rules\PhoneNumber;
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
            'phone' => ['required', 'string', 'max:20', new PhoneNumber],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
