<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\User;
use App\Services\Dashboard\Widget;

/** The caller's own account — never anyone else's. */
final class ProfileWidget extends Widget
{
    public function key(): string
    {
        return 'profile';
    }

    public function title(): string
    {
        return 'My account';
    }

    public function audiences(): array
    {
        return DashboardAudience::cases();
    }

    protected function data(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
        ];
    }
}
