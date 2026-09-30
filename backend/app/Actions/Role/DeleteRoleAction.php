<?php

namespace App\Actions\Role;

use App\Support\SystemRoles;
use Spatie\Permission\Models\Role;

class DeleteRoleAction
{
    public function execute(Role $role): void
    {
        if (SystemRoles::isProtected($role->name)) {
            abort(403, 'System roles cannot be deleted.');
        }

        $role->delete();
    }
}
