<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Enums\Gender;
use App\Models\Student;
use App\Models\User;
use App\Services\Dashboard\Widget;
use App\Support\SchoolClock;

/** Student headcount (archived excluded), with this month's admissions and a gender split. */
final class StudentStatsWidget extends Widget
{
    public function key(): string
    {
        return 'student_stats';
    }

    public function title(): string
    {
        return 'Students';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Staff];
    }

    public function permission(): string
    {
        return 'student.view';
    }

    protected function data(User $user): array
    {
        $counts = Student::query()
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        return [
            'total' => (int) $counts->sum(),
            'admitted_this_month' => Student::query()
                ->where('admission_date', '>=', SchoolClock::today()->startOfMonth())
                ->count(),
            'archived' => Student::onlyTrashed()->count(),
            'by_gender' => array_map(fn (Gender $gender) => [
                'gender' => $gender->value,
                'label' => $gender->label(),
                'count' => (int) ($counts[$gender->value] ?? 0),
            ], Gender::cases()),
        ];
    }
}
