<?php

namespace App\Actions\User;

use App\Models\User;

class ToggleStatusAction
{
    public function execute(User $user): User
    {
        $user->update(['is_active' => ! $user->is_active]);

        return $user->load('roles');
    }
}
