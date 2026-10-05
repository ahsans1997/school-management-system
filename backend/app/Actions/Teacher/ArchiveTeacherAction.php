<?php

namespace App\Actions\Teacher;

use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

class ArchiveTeacherAction
{
    /**
     * Archive (soft delete) a teacher who has left. Their classes must be
     * handed over first, so no section is left without its teacher; their
     * login is switched off in the same step.
     */
    public function execute(Teacher $teacher): void
    {
        abort_if(
            $teacher->assignments()->exists() || $teacher->classTeacherOf()->exists(),
            409,
            'This teacher still has subjects or a class assigned. Hand them over first.',
        );

        DB::transaction(function () use ($teacher) {
            $teacher->account?->update(['is_active' => false]);
            $teacher->delete();
        });
    }
}
