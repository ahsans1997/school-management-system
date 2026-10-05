<?php

namespace App\Http\Controllers;

use App\Actions\Files\DeleteDocumentAction;
use App\Actions\Files\StoreDocumentAction;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StudentDocumentController extends Controller
{
    public function store(StoreDocumentRequest $request, Student $student, StoreDocumentAction $action): JsonResponse
    {
        $document = $action->execute($student, $request->string('title')->toString(), $request->file('file'), $request->user());

        return (new DocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    /** {document} is scoped to {student} by the route. */
    public function destroy(Student $student, Document $document, DeleteDocumentAction $action): Response
    {
        $action->execute($document);

        return response()->noContent();
    }
}
