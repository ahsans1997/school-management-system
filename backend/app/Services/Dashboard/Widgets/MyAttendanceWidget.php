<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Dashboard\Widget;

/** A student's own attendance this year — scoped by the login's student link. */
final class MyAttendanceWidget extends Widget
{
    public function key(): string
    {
        return 'my_attendance';
    }

    public function title(): string
    {
        return 'My attendance';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Student];
    }

    protected function data(User $user): array
    {
        $student = $user->personable_type === (new Student)->getMorphClass()
            ? Student::find($user->personable_id)
            : null;
        $year = AcademicYear::current();

        if ($student === null || $year === null) {
            return ['linked' => $student !== null, 'summary' => null];
        }

        return [
            'linked' => true,
            'academic_year' => $year->name,
            'summary' => app(AttendanceService::class)->forStudent($student, $year)['summary'],
        ];
    }
}
