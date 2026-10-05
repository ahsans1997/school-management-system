<?php

namespace App\Http\Controllers;

use App\Actions\Student\GuardianLinks;
use App\Http\Requests\Student\LinkGuardianRequest;
use App\Http\Requests\Student\UpdateGuardianLinkRequest;
use App\Http\Resources\StudentResource;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

/** A student's guardians. Each change returns the student with its updated guardian list. */
class StudentGuardianController extends Controller
{
    public function store(LinkGuardianRequest $request, Student $student, GuardianLinks $links): JsonResponse
    {
        $links->link(
            $student,
            Guardian::findOrFail($request->integer('guardian_id')),
            $request->string('relationship')->toString(),
            $request->boolean('is_primary'),
        );

        return $this->withGuardians($student)->response()->setStatusCode(201);
    }

    public function update(UpdateGuardianLinkRequest $request, Student $student, Guardian $guardian, GuardianLinks $links): StudentResource
    {
        $links->update(
            $student,
            $guardian,
            $request->string('relationship')->toString(),
            $request->has('is_primary') ? $request->boolean('is_primary') : null,
        );

        return $this->withGuardians($student);
    }

    public function destroy(Student $student, Guardian $guardian, GuardianLinks $links): StudentResource
    {
        $links->unlink($student, $guardian);

        return $this->withGuardians($student);
    }

    private function withGuardians(Student $student): StudentResource
    {
        return new StudentResource($student->load('guardians'));
    }
}
