<?php

namespace App\Actions\Role;

use Spatie\Permission\Models\Role;

class CreateRoleAction
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function execute(string $name, array $permissions = []): Role
    {
        $role = Role::create([
            'name' => $name,
            'guard_name' => config('permissions.guard', 'api'),
        ]);

        if ($permissions !== []) {
            $role->syncPermissions($permissions);
        }

        return $role->load('permissions');
    }
}
