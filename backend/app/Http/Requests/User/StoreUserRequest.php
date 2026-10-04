<?php

namespace App\Http\Requests\User;

use App\Models\User;
use App\Rules\PersonLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        $guard = config('permissions.guard', 'api');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', $guard)],
            'personable_type' => ['nullable', 'string', Rule::in(User::PERSON_TYPES), 'required_with:personable_id'],
            'personable_id' => ['nullable', 'integer', 'required_with:personable_type', new PersonLink],
        ];
    }
}
