<?php

namespace App\Services\Dashboard;

use App\Enums\DashboardAudience;
use App\Models\User;

/**
 * A dashboard widget. Visibility is two-layered: the caller's audience
 * must be listed AND they must hold the widget's permission (if any).
 * Data is always computed for — and scoped to — the calling user.
 */
abstract class Widget
{
    abstract public function key(): string;

    abstract public function title(): string;

    /** @return list<DashboardAudience> */
    abstract public function audiences(): array;

    /** Permission the caller must hold, or null for any authenticated user. */
    public function permission(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function data(User $user): array;

    public function visibleTo(User $user, DashboardAudience $audience): bool
    {
        if (! in_array($audience, $this->audiences(), true)) {
            return false;
        }

        $permission = $this->permission();

        return $permission === null || $user->can($permission);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(User $user): array
    {
        return [
            'key' => $this->key(),
            'title' => $this->title(),
            'status' => 'ready',
            'data' => $this->data($user),
        ];
    }
}
