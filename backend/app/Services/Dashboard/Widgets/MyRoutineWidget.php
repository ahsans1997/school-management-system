<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\User;
use App\Services\Dashboard\Widget;
use App\Services\Routine\RoutineService;
use App\Support\SchoolClock;

/** A student's periods today, from their own section's routine. */
final class MyRoutineWidget extends Widget
{
    public function key(): string
    {
        return 'my_routine';
    }

    public function title(): string
    {
        return 'My routine';
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
        $today = Weekday::fromDate(SchoolClock::today());
        $enrollment = $student && $year
            ? $student->enrollments()->where('academic_year_id', $year->id)->active()->with('section')->first()
            : null;

        if ($enrollment === null) {
            return ['linked' => $student !== null, 'day' => $today->value, 'periods' => []];
        }

        $slots = app(RoutineService::class)->forSection($enrollment->section, $year)['slots'];

        return [
            'linked' => true,
            'day' => $today->value,
            'periods' => array_values(array_filter($slots, fn (array $slot) => $slot['day'] === $today->value)),
        ];
    }
}
