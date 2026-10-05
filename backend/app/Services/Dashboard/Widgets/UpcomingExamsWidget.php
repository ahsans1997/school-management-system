<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\User;
use App\Services\Dashboard\Widget;
use App\Services\Exam\ExamScheduleService;
use Illuminate\Support\Facades\DB;

/**
 * Exams still to come or under way this year. Admins (with exam.view) see
 * every exam; teachers, students and parents only those with papers for
 * their own sections.
 */
final class UpcomingExamsWidget extends Widget
{
    private const LIMIT = 5;

    public function key(): string
    {
        return 'upcoming_exams';
    }

    public function title(): string
    {
        return 'Upcoming exams';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Teacher, DashboardAudience::Parent, DashboardAudience::Student];
    }

    public function visibleTo(User $user, DashboardAudience $audience): bool
    {
        // Admins need the permission; the portals see only their own exams.
        return parent::visibleTo($user, $audience)
            && ($audience !== DashboardAudience::Admin || $user->can('exam.view'));
    }

    protected function data(User $user): array
    {
        $year = AcademicYear::current();
        if ($year === null) {
            return ['exams' => []];
        }
        $schedules = app(ExamScheduleService::class);

        if (DashboardAudience::forUser($user) === DashboardAudience::Admin) {
            return ['exams' => $schedules->upcoming($year)
                ->withCount(['subjects as papers_count', 'subjects as classes_count' => fn ($q) => $q->select(DB::raw('count(distinct class_id)'))])
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (Exam $e) => [...$schedules->header($e), 'papers' => $e->papers_count, 'classes' => $e->classes_count])
                ->all()];
        }

        // Each exam once, even when it covers several of the caller's sections.
        $exams = collect($schedules->mine($user, $year)['groups'])
            ->flatMap(fn (array $group) => $group['exams'])
            ->unique('id')
            ->sortBy([['start_date', 'asc'], ['name', 'asc']])
            ->take(self::LIMIT)
            ->map(fn (array $e) => ['id' => $e['id'], 'name' => $e['name'], 'type' => $e['type'], 'start_date' => $e['start_date'], 'end_date' => $e['end_date']])
            ->values()
            ->all();

        return ['exams' => $exams];
    }
}
