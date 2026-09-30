<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\User;
use App\Services\Dashboard\Widget;

/** The five most recently created accounts (roles eager-loaded — no N+1). */
final class RecentUsersWidget extends Widget
{
    private const LIMIT = 5;

    public function key(): string
    {
        return 'recent_users';
    }

    public function title(): string
    {
        return 'Recently added users';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin, DashboardAudience::Staff];
    }

    public function permission(): ?string
    {
        return 'user.view';
    }

    protected function data(User $user): array
    {
        return [
            'items' => User::query()
                ->with('roles')
                ->latest('id')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->roles->pluck('name')->values()->all(),
                    'is_active' => $u->is_active,
                    'created_at' => $u->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
