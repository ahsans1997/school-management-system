<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\AccountRules;
use App\Models\Teacher;

class StoreTeacherRequest extends TeacherRequest
{
    /**
     * teacher.create is checked by middleware. Creating a login in the same
     * request also needs teacher.edit, the permission for adding one later.
     */
    public function authorize(): bool
    {
        return ! $this->filled('account') || $this->user()->hasPermissionTo('teacher.edit');
    }

    protected function teacher(): ?Teacher
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // Optional Teacher-portal login, created in the same transaction.
            'account' => ['nullable', 'array:email,password'],
            ...AccountRules::rules('account.'),
        ];
    }
}
