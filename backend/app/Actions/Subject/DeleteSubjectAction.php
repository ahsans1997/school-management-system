<?php

namespace App\Actions\Subject;

use App\Models\Subject;
use App\Support\GuardedDelete;

class DeleteSubjectAction
{
    /** "In use" is enforced by FK RESTRICT on class_subjects (and later exams/marks). */
    public function execute(Subject $subject): void
    {
        GuardedDelete::delete(
            $subject,
            'This subject is assigned to a class (or otherwise in use) and cannot be deleted. Unassign it first.',
        );
    }
}
