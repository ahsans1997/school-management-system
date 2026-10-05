<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\User;
use App\Services\Dashboard\Widget;
use Spatie\Permission\Models\Role;

/** Account totals and a per-role breakdown. Soft-deleted users are excluded. */
final class UserStatsWidget extends Widget
{
    public function key(): string
    {
        return 'user_stats';
    }

    public function title(): string
    {
        return 'Users';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Staff];
    }

    public function permission(): string
    {
        return 'user.view';
    }

    protected function data(User $user): array
    {
        $total = User::query()->count();
        $active = User::query()->where('is_active', true)->count();

        $byRole = Role::query()
            ->where('guard_name', config('permissions.guard', 'api'))
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => ['role' => $role->name, 'count' => $role->users_count])
            ->values()
            ->all();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'by_role' => $byRole,
        ];
    }
}
