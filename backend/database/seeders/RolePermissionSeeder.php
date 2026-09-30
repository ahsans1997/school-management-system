<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the canonical permissions and roles from config/permissions.php.
     * Idempotent — safe to re-run.
     */
    public function run(): void
    {
        $guard = config('permissions.guard', 'api');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Every permission from the catalogue.
        $allPermissions = collect(config('permissions.groups'))
            ->flatten()
            ->unique()
            ->values();

        foreach ($allPermissions as $name) {
            Permission::findOrCreate($name, $guard);
        }

        // Refresh the registrar so role assignment sees the new permissions.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Roles and their granted permissions.
        foreach (config('permissions.roles') as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, $guard);
            $grant = $permissions === '*' ? $allPermissions->all() : $permissions;
            $role->syncPermissions($grant);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
