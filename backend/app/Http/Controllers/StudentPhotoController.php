<?php

namespace App\Http\Controllers;

use App\Actions\Student\RemoveStudentPhotoAction;
use App\Actions\Student\StoreStudentPhotoAction;
use App\Http\Requests\Student\UploadStudentPhotoRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;

class StudentPhotoController extends Controller
{
    public function store(UploadStudentPhotoRequest $request, Student $student, StoreStudentPhotoAction $action): StudentResource
    {
        return new StudentResource($action->execute($student, $request->file('photo')));
    }

    public function destroy(Student $student, RemoveStudentPhotoAction $action): StudentResource
    {
        return new StudentResource($action->execute($student));
    }
}
