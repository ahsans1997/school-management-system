<?php

namespace App\Actions\Subject;

use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;

class AssignSubjectAction
{
    public function execute(SchoolClass $class, Subject $subject): ClassSubject
    {
        return ClassSubject::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ])->setRelation('subject', $subject);
    }
}
