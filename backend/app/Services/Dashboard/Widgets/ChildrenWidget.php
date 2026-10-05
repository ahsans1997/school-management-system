<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Services\Dashboard\Widget;

/**
 * A parent's own children — scoped by ownership, not permission: only the
 * students linked to the guardian record this login belongs to.
 */
final class ChildrenWidget extends Widget
{
    public function key(): string
    {
        return 'children';
    }

    public function title(): string
    {
        return 'My children';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Parent];
    }

    protected function data(User $user): array
    {
        // Check the type before resolving: person types whose module isn't
        // built yet have no model to resolve to.
        $guardian = $user->personable_type === (new Guardian)->getMorphClass()
            ? Guardian::find($user->personable_id)
            : null;

        if ($guardian === null) {
            return ['linked' => false, 'items' => []];
        }

        return [
            'linked' => true,
            'items' => $guardian->students()
                ->orderBy('name')
                ->get()
                ->map(fn (Student $student) => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'student_id' => $student->student_id,
                    'relationship' => $student->pivot->relationship,
                ])
                ->all(),
        ];
    }
}
