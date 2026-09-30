<?php

namespace App\Actions\User;

use App\Models\User;

class AssignRoleAction
{
    /**
     * Replace the user's roles with the given set (validated server-side).
     *
     * @param  array<int, string>  $roles
     */
    public function execute(User $user, array $roles): User
    {
        $user->syncRoles($roles);

        return $user->load('roles');
    }
}
