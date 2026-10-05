<?php

namespace App\Http\Controllers;

use App\Actions\Files\RemovePhotoAction;
use App\Actions\Files\StorePhotoAction;
use App\Http\Requests\UploadPhotoRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;

class StudentPhotoController extends Controller
{
    public function store(UploadPhotoRequest $request, Student $student, StorePhotoAction $action): StudentResource
    {
        return new StudentResource($action->execute($student, $request->file('photo')));
    }

    public function destroy(Student $student, RemovePhotoAction $action): StudentResource
    {
        return new StudentResource($action->execute($student));
    }
}
