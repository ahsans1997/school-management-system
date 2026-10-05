<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Enums\Weekday;
use App\Models\AcademicYear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Dashboard\Widget;
use App\Services\Routine\RoutineService;

/** A teacher's periods today, from their own routine. */
final class TodaysClassesWidget extends Widget
{
    public function key(): string
    {
        return 'todays_classes';
    }

    public function title(): string
    {
        return "Today's classes";
    }

    public function audiences(): array
    {
        return [DashboardAudience::Teacher];
    }

    protected function data(User $user): array
    {
        $teacher = Teacher::forUser($user);
        $year = AcademicYear::current();
        $today = Weekday::fromDate(today());

        if ($teacher === null || $year === null) {
            return ['linked' => $teacher !== null, 'day' => $today->value, 'periods' => []];
        }

        return [
            'linked' => true,
            'day' => $today->value,
            'periods' => array_values(array_filter(
                app(RoutineService::class)->forTeacher($teacher, $year),
                fn (array $slot) => $slot['day'] === $today->value,
            )),
        ];
    }
}
