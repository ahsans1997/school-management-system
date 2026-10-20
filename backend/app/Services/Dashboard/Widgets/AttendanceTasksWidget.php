<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Dashboard\Widget;
use App\Support\SchoolClock;

/** A teacher's own sections and whether today's attendance is taken. */
final class AttendanceTasksWidget extends Widget
{
    public function key(): string
    {
        return 'attendance_tasks';
    }

    public function title(): string
    {
        return 'Attendance to take';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Teacher];
    }

    public function permission(): string
    {
        return 'attendance.create';
    }

    protected function data(User $user): array
    {
        $teacher = Teacher::forUser($user);
        if ($teacher === null) {
            return ['linked' => false, 'sections' => []];
        }

        $today = SchoolClock::date();
        $sections = Section::query()
            ->with('schoolClass')
            ->whereKey($teacher->sectionIds())
            ->withExists(['attendances as marked' => fn ($q) => $q->whereDate('date', $today)])
            ->get()
            ->sortBy(fn (Section $s) => [$s->schoolClass->numeric_level, $s->name])
            ->values();

        return [
            'linked' => true,
            'date' => $today,
            'sections' => $sections->map(fn (Section $s) => [
                'section_id' => $s->id,
                'class' => $s->schoolClass->name,
                'section' => $s->name,
                'marked' => (bool) $s->marked,
            ])->all(),
        ];
    }
}
