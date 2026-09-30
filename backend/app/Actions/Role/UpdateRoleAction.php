<?php

namespace App\Actions\Role;

use App\Support\SystemRoles;
use Spatie\Permission\Models\Role;

class UpdateRoleAction
{
    public function execute(Role $role, string $name): Role
    {
        if ($name !== $role->name && SystemRoles::isProtected($role->name)) {
            abort(403, 'System roles cannot be renamed.');
        }

        $role->update(['name' => $name]);

        return $role->load('permissions');
    }
}
