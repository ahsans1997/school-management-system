<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', $guard)
                    ->ignore($this->route('role')),
            ],
        ];
    }
}
