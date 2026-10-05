<?php

namespace App\Support;

use App\Enums\DashboardAudience;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\User;

/**
 * The ownership layer for teachers (spec §52): a teacher reaches only the
 * sections they teach a subject in or are class teacher of. Callers whose
 * audience isn't Teacher (admin, staff) are not section-scoped — their
 * permission alone decides.
 */
final class TeacherScope
{
    /**
     * Section ids the user is limited to, or null when they aren't scoped.
     * A Teacher-role login with no linked teacher record gets [] (nothing).
     *
     * @return list<int>|null
     */
    public static function sectionIds(User $user): ?array
    {
        if (DashboardAudience::forUser($user) !== DashboardAudience::Teacher) {
            return null;
        }

        return Teacher::forUser($user)?->sectionIds() ?? [];
    }

    public static function allows(User $user, Section $section): bool
    {
        $ids = self::sectionIds($user);

        return $ids === null || in_array($section->id, $ids, true);
    }

    /** 403 unless the user may work with this section. */
    public static function authorize(User $user, Section $section): void
    {
        abort_unless(self::allows($user, $section), 403, 'You can only work with the sections you teach.');
    }
}
