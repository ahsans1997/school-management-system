<?php

namespace App\Support;

class SystemRoles
{
    public const ADMIN = 'Admin';

    /**
     * Whether a role is a protected system role (cannot be deleted or renamed).
     */
    public static function isProtected(string $name): bool
    {
        return in_array($name, config('permissions.system_roles', []), true);
    }
}
