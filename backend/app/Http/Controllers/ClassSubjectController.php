<?php

namespace App\Http\Controllers;

use App\Actions\Subject\AssignSubjectAction;
use App\Actions\Subject\UnassignSubjectAction;
use App\Http\Requests\Subject\AssignSubjectRequest;
use App\Http\Resources\ClassSubjectResource;
use App\Http\Resources\SchoolClassResource;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** The subjects taught in a class (the class_subjects pivot). */
class ClassSubjectController extends Controller
{
    public function index(SchoolClass $schoolClass): AnonymousResourceCollection
    {
        $assignments = ClassSubject::query()
            ->where('class_id', $schoolClass->id)
            ->with('subject')
            ->orderBy(Subject::select('name')->whereColumn('subjects.id', 'class_subjects.subject_id'))
            ->paginate(PerPage::from(request()));

        // The parent class rides along so the screen needs a single request.
        return ClassSubjectResource::collection($assignments)
            ->additional(['class' => new SchoolClassResource($schoolClass)]);
    }

    public function store(
        AssignSubjectRequest $request,
        SchoolClass $schoolClass,
        AssignSubjectAction $action,
    ): JsonResponse {
        $subject = Subject::findOrFail($request->integer('subject_id'));

        return (new ClassSubjectResource($action->execute($schoolClass, $subject)))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(SchoolClass $schoolClass, Subject $subject, UnassignSubjectAction $action): Response
    {
        $action->execute($schoolClass, $subject);

        return response()->noContent();
    }
}
