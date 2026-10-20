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
        if (! self::applies($user)) {
            return null;
        }

        return Teacher::forUser($user)?->sectionIds() ?? [];
    }

    /** Whether the user is limited to their own teaching (a Teacher-audience login). */
    public static function applies(User $user): bool
    {
        return DashboardAudience::forUser($user) === DashboardAudience::Teacher;
    }

    /**
     * Whether subject-level work (marks) is limited to the user's own
     * teaching. Fails closed: only exam admins (exam.edit) are unscoped, so
     * any other role given exam.marks_entry still needs a linked teacher
     * record and the matching teaching assignment.
     */
    public static function subjectScoped(User $user): bool
    {
        return ! $user->can('exam.edit');
    }

    /**
     * Subject-level scope (marks): the teacher teaches this class subject in
     * this section — their own teaching_assignments row. Being class teacher
     * of the section isn't enough.
     */
    public static function teaches(User $user, Section $section, int $classSubjectId): bool
    {
        if (! self::subjectScoped($user)) {
            return true;
        }

        return (bool) Teacher::forUser($user)?->assignments()
            ->where('section_id', $section->id)
            ->where('class_subject_id', $classSubjectId)
            ->exists();
    }

    /** 403 unless the user may work with this subject in this section. */
    public static function authorizeSubject(User $user, Section $section, int $classSubjectId): void
    {
        abort_unless(self::teaches($user, $section, $classSubjectId), 403, 'You can only enter marks for the subjects you teach.');
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
