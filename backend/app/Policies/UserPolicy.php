<?php

namespace App\Policies;

use App\Models\User;

/**
 * Record-level (ownership) authorization — the second layer on top of the
 * `user.*` permission middleware. These rules prevent a user from acting
 * destructively on their own account or escalating their own privileges.
 */
class UserPolicy
{
    /** A user may not assign roles to themselves (self-escalation guard). */
    public function assignRole(User $actor, User $target): bool
    {
        return $actor->id !== $target->id;
    }

    /** A user may not activate/deactivate their own account. */
    public function toggleStatus(User $actor, User $target): bool
    {
        return $actor->id !== $target->id;
    }

    /** A user may not delete their own account. */
    public function delete(User $actor, User $target): bool
    {
        return $actor->id !== $target->id;
    }
}
