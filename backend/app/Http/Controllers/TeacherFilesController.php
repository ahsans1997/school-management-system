<?php

namespace App\Http\Controllers;

use App\Actions\Files\DeleteDocumentAction;
use App\Actions\Files\RemovePhotoAction;
use App\Actions\Files\StoreDocumentAction;
use App\Actions\Files\StorePhotoAction;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UploadPhotoRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\TeacherResource;
use App\Models\Document;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** A teacher's photo and documents (shared private-file actions). */
class TeacherFilesController extends Controller
{
    public function storePhoto(UploadPhotoRequest $request, Teacher $teacher, StorePhotoAction $action): TeacherResource
    {
        return new TeacherResource($action->execute($teacher, $request->file('photo')));
    }

    public function destroyPhoto(Teacher $teacher, RemovePhotoAction $action): TeacherResource
    {
        return new TeacherResource($action->execute($teacher));
    }

    public function storeDocument(StoreDocumentRequest $request, Teacher $teacher, StoreDocumentAction $action): JsonResponse
    {
        $document = $action->execute($teacher, $request->string('title')->toString(), $request->file('file'), $request->user());

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }

    /** {document} is scoped to {teacher} by the route. */
    public function destroyDocument(Teacher $teacher, Document $document, DeleteDocumentAction $action): Response
    {
        $action->execute($document);

        return response()->noContent();
    }
}
