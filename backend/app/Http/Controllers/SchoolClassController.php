<?php

namespace App\Http\Controllers;

use App\Actions\SchoolClass\CreateSchoolClassAction;
use App\Actions\SchoolClass\DeleteSchoolClassAction;
use App\Actions\SchoolClass\UpdateSchoolClassAction;
use App\Http\Requests\SchoolClass\StoreSchoolClassRequest;
use App\Http\Requests\SchoolClass\UpdateSchoolClassRequest;
use App\Http\Resources\SchoolClassResource;
use App\Models\SchoolClass;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SchoolClassController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $classes = SchoolClass::query()
            ->withCount('sections')
            ->orderBy('numeric_level')
            ->orderBy('name')
            ->paginate(PerPage::from(request()));

        return SchoolClassResource::collection($classes);
    }

    public function store(StoreSchoolClassRequest $request, CreateSchoolClassAction $action): JsonResponse
    {
        return (new SchoolClassResource($action->execute($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(SchoolClass $schoolClass): SchoolClassResource
    {
        return new SchoolClassResource($schoolClass);
    }

    public function update(
        UpdateSchoolClassRequest $request,
        SchoolClass $schoolClass,
        UpdateSchoolClassAction $action,
    ): SchoolClassResource {
        return new SchoolClassResource($action->execute($schoolClass, $request->validated()));
    }

    public function destroy(SchoolClass $schoolClass, DeleteSchoolClassAction $action): Response
    {
        $action->execute($schoolClass);

        return response()->noContent();
    }
}
