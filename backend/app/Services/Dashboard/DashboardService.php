<?php

namespace App\Services\Dashboard;

use App\Enums\DashboardAudience as A;
use App\Models\User;
use App\Services\Dashboard\Widgets\AssignedSubjectsWidget;
use App\Services\Dashboard\Widgets\AttendanceTasksWidget;
use App\Services\Dashboard\Widgets\AttendanceTodayWidget;
use App\Services\Dashboard\Widgets\ChildAttendanceWidget;
use App\Services\Dashboard\Widgets\ChildrenWidget;
use App\Services\Dashboard\Widgets\MyAttendanceWidget;
use App\Services\Dashboard\Widgets\MyRoutineWidget;
use App\Services\Dashboard\Widgets\PendingMarksWidget;
use App\Services\Dashboard\Widgets\PendingWidget;
use App\Services\Dashboard\Widgets\ProfileWidget;
use App\Services\Dashboard\Widgets\PublishedResultsWidget;
use App\Services\Dashboard\Widgets\RecentUsersWidget;
use App\Services\Dashboard\Widgets\SchoolWidget;
use App\Services\Dashboard\Widgets\StudentStatsWidget;
use App\Services\Dashboard\Widgets\TeacherStatsWidget;
use App\Services\Dashboard\Widgets\TodaysClassesWidget;
use App\Services\Dashboard\Widgets\UpcomingExamsWidget;
use App\Services\Dashboard\Widgets\UserStatsWidget;

class DashboardService
{
    /**
     * The widget registry (spec §7). Pending entries are placeholders for
     * modules not built yet: when a module lands, swap its PendingWidget for
     * a real widget with the SAME key, scoped to the caller (ownership).
     *
     * @return list<Widget>
     */
    protected function widgets(): array
    {
        $everyone = A::cases();
        $management = [A::Admin, A::Staff];

        return [
            // Live now.
            new ProfileWidget,
            new SchoolWidget,
            new UserStatsWidget,
            new RecentUsersWidget,
            new StudentStatsWidget,
            new ChildrenWidget,
            new TeacherStatsWidget,
            new AssignedSubjectsWidget,
            new AttendanceTodayWidget,
            new AttendanceTasksWidget,
            new ChildAttendanceWidget,
            new MyAttendanceWidget,
            new TodaysClassesWidget,
            new MyRoutineWidget,
            new UpcomingExamsWidget,
            new PendingMarksWidget,
            new PublishedResultsWidget(A::Parent),
            new PublishedResultsWidget(A::Student),

            // Admin / staff — each gated by the permission its data needs.
            new PendingWidget('pending_fees', 'Pending fees', '19 — Fees', $management, 'fee.view'),
            new PendingWidget('monthly_finance', 'Income & expense (this month)', '21 — Expenses & Basic Accounts', $management, 'accounts.view'),
            new PendingWidget('recent_admissions', 'Recent admissions', '31 — Online Admission', $management, 'admission.view'),

            // Parent — will be scoped to the guardian's own children only.
            new PendingWidget('child_fees', 'Fees & dues', '19 — Fees', [A::Parent]),

            // Student — will be scoped to the student's own record only.
            new PendingWidget('my_fees', 'My fees', '19 — Fees', [A::Student]),

            // Shared.
            new PendingWidget('homework', 'Homework', '34 — Teacher Portal', [A::Teacher, A::Parent, A::Student]),
            new PendingWidget('recent_notices', 'Notices', '25 — Notices', $everyone),
            new PendingWidget('upcoming_events', 'Upcoming events', '26 — Events', $everyone),
        ];
    }

    /**
     * @return array{audience: string, generated_at: string, widgets: list<array<string, mixed>>}
     */
    public function build(User $user): array
    {
        $audience = A::forUser($user);

        $widgets = collect($this->widgets())
            ->filter(fn (Widget $widget) => $widget->visibleTo($user, $audience))
            ->map(fn (Widget $widget) => $widget->toPayload($user))
            ->values()
            ->all();

        return [
            'audience' => $audience->value,
            'generated_at' => now()->toIso8601String(),
            'widgets' => $widgets,
        ];
    }
}
