<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Dashboard\Widget;

/**
 * A teacher's own subjects and class-teacher sections — scoped by
 * ownership: only the teacher record this login belongs to.
 */
final class AssignedSubjectsWidget extends Widget
{
    public function key(): string
    {
        return 'assigned_subjects';
    }

    public function title(): string
    {
        return 'My subjects';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Teacher];
    }

    protected function data(User $user): array
    {
        // Check the type before resolving: person types whose module isn't
        // built yet have no model to resolve to.
        $teacher = $user->personable_type === (new Teacher)->getMorphClass()
            ? Teacher::query()->with([
                'assignments.section.schoolClass',
                'assignments.classSubject.subject',
                'classTeacherOf.schoolClass',
            ])->find($user->personable_id)
            : null;

        if ($teacher === null) {
            return ['linked' => false, 'subjects' => [], 'class_teacher_of' => []];
        }

        return [
            'linked' => true,
            'subjects' => $teacher->assignments
                ->sortBy(fn (TeachingAssignment $a) => [$a->section->schoolClass->numeric_level, $a->section->name])
                ->values()
                ->map(fn (TeachingAssignment $a) => [
                    'class' => $a->section->schoolClass->name,
                    'section' => $a->section->name,
                    'subject' => $a->classSubject->subject->name,
                ])
                ->all(),
            'class_teacher_of' => $teacher->classTeacherOf
                ->map(fn (Section $s) => ['class' => $s->schoolClass->name, 'section' => $s->name])
                ->values()
                ->all(),
        ];
    }
}
