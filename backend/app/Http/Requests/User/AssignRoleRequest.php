<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware + UserPolicy (no self-escalation).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $guard = config('permissions.guard', 'api');

        return [
            // `present` allows an empty array to clear all roles.
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', $guard)],
        ];
    }
}
