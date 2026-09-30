<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by the permission: middleware.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $guard = config('permissions.guard', 'api');

        return [
            // `present` allows an empty array to clear all permissions.
            'permissions' => ['present', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', $guard),
            ],
        ];
    }
}
