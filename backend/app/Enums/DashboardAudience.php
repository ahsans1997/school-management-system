<?php

namespace App\Enums;

use App\Models\User;

/**
 * Which dashboard (and portal area) a user gets. Derived from roles; the
 * payload itself is always scoped server-side to the caller.
 */
enum DashboardAudience: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Teacher = 'teacher';
    case Parent = 'parent';
    case Student = 'student';

    public const ADMIN_ROLES = ['Admin', 'Principal'];

    public const PORTAL_ONLY_ROLES = ['Parent', 'Student'];

    /**
     * Precedence: admin > teacher > staff (any other role) > parent > student.
     * A user with no roles falls back to the minimal staff dashboard.
     */
    public static function forUser(User $user): self
    {
        $roles = $user->getRoleNames();

        return match (true) {
            $roles->intersect(self::ADMIN_ROLES)->isNotEmpty() => self::Admin,
            $roles->contains('Teacher') => self::Teacher,
            $roles->diff(self::PORTAL_ONLY_ROLES)->isNotEmpty() => self::Staff,
            $roles->contains('Parent') => self::Parent,
            $roles->contains('Student') => self::Student,
            default => self::Staff,
        };
    }
}
