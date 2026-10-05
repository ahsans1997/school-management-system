<?php

namespace App\Http\Controllers;

use App\Actions\Student\DeleteStudentDocumentAction;
use App\Actions\Student\StoreStudentDocumentAction;
use App\Http\Requests\Student\StoreStudentDocumentRequest;
use App\Http\Resources\StudentDocumentResource;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StudentDocumentController extends Controller
{
    public function store(StoreStudentDocumentRequest $request, Student $student, StoreStudentDocumentAction $action): JsonResponse
    {
        $document = $action->execute($student, $request->string('title')->toString(), $request->file('file'), $request->user());

        return (new StudentDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    /** {document} is scoped to {student} by the route. */
    public function destroy(Student $student, StudentDocument $document, DeleteStudentDocumentAction $action): Response
    {
        $action->execute($document);

        return response()->noContent();
    }
}
