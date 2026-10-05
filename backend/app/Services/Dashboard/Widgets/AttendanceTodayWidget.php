<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceDetail;
use App\Models\Section;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Dashboard\Widget;

/** School-wide: how many sections have taken attendance today, and the day's totals. */
final class AttendanceTodayWidget extends Widget
{
    public function key(): string
    {
        return 'attendance_today';
    }

    public function title(): string
    {
        return "Today's attendance";
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin];
    }

    public function permission(): string
    {
        return 'attendance.view';
    }

    protected function data(User $user): array
    {
        $today = today()->toDateString();
        $year = AcademicYear::current();

        // Sections with at least one active student this year.
        $sections = Section::query()
            ->whereHas('enrollments', fn ($q) => $q->where('academic_year_id', $year->id ?? 0)
                ->where('status', EnrollmentStatus::Active->value))
            ->withExists(['attendances as marked' => fn ($q) => $q->whereDate('date', $today)])
            ->get();

        $summary = app(AttendanceService::class)->summarize(
            AttendanceDetail::query()->whereDate('date', $today)->get(),
        );

        return [
            'date' => $today,
            'sections_total' => $sections->count(),
            'sections_marked' => $sections->where('marked', true)->count(),
            ...$summary,
        ];
    }
}
