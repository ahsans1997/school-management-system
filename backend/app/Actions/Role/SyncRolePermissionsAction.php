<?php

namespace App\Actions\Role;

use App\Support\SystemRoles;
use Spatie\Permission\Models\Role;

class SyncRolePermissionsAction
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function execute(Role $role, array $permissions): Role
    {
        if ($role->name === SystemRoles::ADMIN) {
            abort(403, 'The Admin role always holds every permission and cannot be modified.');
        }

        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }
}
