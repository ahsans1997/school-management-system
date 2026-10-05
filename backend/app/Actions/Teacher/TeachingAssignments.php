<?php

namespace App\Actions\Teacher;

use App\Models\ClassSubject;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Validation\ValidationException;

/**
 * Who teaches what, per section, and who is each section's class teacher.
 * These rows are what later modules scope teacher access by (spec §52).
 */
class TeachingAssignments
{
    /** Teach $subject in $section. The subject must be taught in the section's class. */
    public function assign(Teacher $teacher, Section $section, Subject $subject): TeachingAssignment
    {
        $section->loadMissing('schoolClass');
        $where = "{$section->schoolClass->name}, section {$section->name}";

        $classSubject = ClassSubject::query()
            ->where('class_id', $section->class_id)
            ->where('subject_id', $subject->id)
            ->first();

        if ($classSubject === null) {
            throw ValidationException::withMessages([
                'subject_id' => "{$subject->name} isn't taught in {$section->schoolClass->name}. Assign it to the class first.",
            ]);
        }

        $existing = TeachingAssignment::query()
            ->with('teacher')
            ->where('section_id', $section->id)
            ->where('class_subject_id', $classSubject->id)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'subject_id' => $existing->teacher_id === $teacher->id
                    ? "This teacher already teaches {$subject->name} in {$where}."
                    : "{$subject->name} in {$where} is taught by {$existing->teacher->name}. Remove that assignment first.",
            ]);
        }

        return $teacher->assignments()->create([
            'section_id' => $section->id,
            'class_subject_id' => $classSubject->id,
        ]);
    }

    /** Make $teacher the class teacher of $section (one per section). */
    public function setClassTeacher(Teacher $teacher, Section $section): void
    {
        $section->loadMissing(['schoolClass', 'classTeacher']);
        $where = "{$section->schoolClass->name}, section {$section->name}";

        if ($section->class_teacher_id !== null) {
            throw ValidationException::withMessages([
                'section_id' => $section->class_teacher_id === $teacher->id
                    ? "This teacher is already the class teacher of {$where}."
                    : "The class teacher of {$where} is {$section->classTeacher->name}. Remove them first.",
            ]);
        }

        $section->forceFill(['class_teacher_id' => $teacher->id])->save();
    }

    /** 404 unless $teacher is the class teacher of $section. */
    public function removeClassTeacher(Teacher $teacher, Section $section): void
    {
        abort_unless($section->class_teacher_id === $teacher->id, 404);

        $section->forceFill(['class_teacher_id' => null])->save();
    }
}
