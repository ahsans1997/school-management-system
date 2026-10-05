<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Dashboard\Widget;

/** Teacher headcount (archived excluded), logins, and who has nothing to teach yet. */
final class TeacherStatsWidget extends Widget
{
    public function key(): string
    {
        return 'teacher_stats';
    }

    public function title(): string
    {
        return 'Teachers';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Staff];
    }

    public function permission(): string
    {
        return 'teacher.view';
    }

    protected function data(User $user): array
    {
        return [
            'total' => Teacher::query()->count(),
            'with_login' => Teacher::query()->whereHas('account')->count(),
            'unassigned' => Teacher::query()->whereDoesntHave('assignments')->whereDoesntHave('classTeacherOf')->count(),
            'archived' => Teacher::onlyTrashed()->count(),
        ];
    }
}
