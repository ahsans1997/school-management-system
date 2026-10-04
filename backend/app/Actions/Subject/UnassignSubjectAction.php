<?php

namespace App\Actions\Subject;

use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\GuardedDelete;

class UnassignSubjectAction
{
    /** 404 when the subject isn't assigned to the class. */
    public function execute(SchoolClass $class, Subject $subject): void
    {
        $assignment = ClassSubject::query()
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->firstOrFail();

        GuardedDelete::delete(
            $assignment,
            'This subject is in use for the class and cannot be unassigned.',
        );
    }
}
