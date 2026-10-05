<?php

namespace App\Http\Controllers;

use App\Actions\Teacher\TeachingAssignments;
use App\Http\Requests\Teacher\AssignTeachingRequest;
use App\Http\Requests\Teacher\SetClassTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Http\JsonResponse;

/**
 * What a teacher teaches (subject per section) and which sections they are
 * class teacher of. Each change returns the teacher's updated assignments.
 */
class TeachingController extends Controller
{
    public function storeSubject(AssignTeachingRequest $request, Teacher $teacher, TeachingAssignments $teaching): JsonResponse
    {
        $teaching->assign(
            $teacher,
            Section::findOrFail($request->integer('section_id')),
            Subject::findOrFail($request->integer('subject_id')),
        );

        return $this->assignmentsOf($teacher)->response()->setStatusCode(201);
    }

    /** {assignment} is scoped to {teacher} by the route. */
    public function destroySubject(Teacher $teacher, TeachingAssignment $assignment): TeacherResource
    {
        $assignment->delete();

        return $this->assignmentsOf($teacher);
    }

    public function storeClass(SetClassTeacherRequest $request, Teacher $teacher, TeachingAssignments $teaching): JsonResponse
    {
        $teaching->setClassTeacher($teacher, Section::findOrFail($request->integer('section_id')));

        return $this->assignmentsOf($teacher)->response()->setStatusCode(201);
    }

    public function destroyClass(Teacher $teacher, Section $section, TeachingAssignments $teaching): TeacherResource
    {
        $teaching->removeClassTeacher($teacher, $section);

        return $this->assignmentsOf($teacher);
    }

    private function assignmentsOf(Teacher $teacher): TeacherResource
    {
        return new TeacherResource($teacher->load([
            'assignments.section.schoolClass',
            'assignments.classSubject.subject',
            'classTeacherOf.schoolClass',
        ]));
    }
}
