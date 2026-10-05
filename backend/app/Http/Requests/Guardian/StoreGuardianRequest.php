<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\AccountRules;

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
            ...AccountRules::rules('account.'),
        ];
    }
}
