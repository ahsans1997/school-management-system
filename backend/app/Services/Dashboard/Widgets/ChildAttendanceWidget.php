<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Dashboard\Widget;

/** A parent's own children's attendance this year — scoped by the guardian link. */
final class ChildAttendanceWidget extends Widget
{
    public function key(): string
    {
        return 'child_attendance';
    }

    public function title(): string
    {
        return 'Attendance';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Parent];
    }

    protected function data(User $user): array
    {
        $guardian = $user->personable_type === (new Guardian)->getMorphClass()
            ? Guardian::find($user->personable_id)
            : null;
        $year = AcademicYear::current();

        if ($guardian === null || $year === null) {
            return ['linked' => $guardian !== null, 'children' => []];
        }

        $service = app(AttendanceService::class);

        return [
            'linked' => true,
            'academic_year' => $year->name,
            'children' => $guardian->students()->orderBy('name')->get()
                ->map(fn (Student $s) => ['name' => $s->name, ...$service->forStudent($s, $year)['summary']])
                ->all(),
        ];
    }
}
