<?php

namespace App\Http\Controllers;

use App\Actions\Subject\CreateSubjectAction;
use App\Actions\Subject\DeleteSubjectAction;
use App\Actions\Subject\UpdateSubjectAction;
use App\Http\Requests\Subject\StoreSubjectRequest;
use App\Http\Requests\Subject\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SubjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $subjects = Subject::query()
            ->withCount('classes')
            ->orderBy('name')
            ->paginate(PerPage::from(request()));

        return SubjectResource::collection($subjects);
    }

    public function store(StoreSubjectRequest $request, CreateSubjectAction $action): JsonResponse
    {
        return (new SubjectResource($action->execute($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Subject $subject): SubjectResource
    {
        return new SubjectResource($subject->loadCount('classes'));
    }

    public function update(
        UpdateSubjectRequest $request,
        Subject $subject,
        UpdateSubjectAction $action,
    ): SubjectResource {
        return new SubjectResource($action->execute($subject, $request->validated()));
    }

    public function destroy(Subject $subject, DeleteSubjectAction $action): Response
    {
        $action->execute($subject);

        return response()->noContent();
    }
}
