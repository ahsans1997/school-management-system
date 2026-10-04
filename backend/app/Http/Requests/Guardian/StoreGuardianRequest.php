<?php

namespace App\Http\Requests\Guardian;

use Illuminate\Validation\Rule;

class StoreGuardianRequest extends GuardianRequest
{
    /**
     * guardian.create is checked by middleware. Creating a login in the same
     * request also needs guardian.edit, the permission for adding one later.
     */
    public function authorize(): bool
    {
        return ! $this->filled('account') || $this->user()->hasPermissionTo('guardian.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Optional Parent-portal login, created in the same transaction.
            'account' => ['nullable', 'array:email,password'],
            ...self::accountRules('account.'),
        ];
    }

    /**
     * Login fields; reused by the standalone "create login" request.
     *
     * @return array<string, mixed>
     */
    public static function accountRules(string $prefix = ''): array
    {
        $required = $prefix === '' ? 'required' : 'required_with:account';

        return [
            "{$prefix}email" => [$required, 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            "{$prefix}password" => [$required, 'string', 'min:8'],
        ];
    }
}
