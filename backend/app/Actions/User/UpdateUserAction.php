<?php

namespace App\Actions\User;

use App\Models\User;

class UpdateUserAction
{
    /**
     * Update profile fields only. Role changes go through AssignRoleAction,
     * status through ToggleStatusAction, password through ResetPasswordAction.
     *
     * @param  array<string, mixed>  $data  Validated fields (only present keys applied).
     */
    public function execute(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user->load('roles');
    }
}
