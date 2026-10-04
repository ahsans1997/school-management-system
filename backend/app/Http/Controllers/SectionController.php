<?php

namespace App\Http\Controllers;

use App\Actions\Section\CreateSectionAction;
use App\Actions\Section\DeleteSectionAction;
use App\Actions\Section\UpdateSectionAction;
use App\Http\Requests\Section\StoreSectionRequest;
use App\Http\Requests\Section\UpdateSectionRequest;
use App\Http\Resources\SchoolClassResource;
use App\Http\Resources\SectionResource;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Sections are listed and created under their class
 * (/classes/{class}/sections) and addressed directly after that (/sections/{id}).
 */
class SectionController extends Controller
{
    public function index(SchoolClass $schoolClass): AnonymousResourceCollection
    {
        $sections = $schoolClass->sections()
            ->orderBy('name')
            ->paginate(PerPage::from(request()));

        // The parent class rides along so the screen needs a single request.
        return SectionResource::collection($sections)
            ->additional(['class' => new SchoolClassResource($schoolClass)]);
    }

    public function store(
        StoreSectionRequest $request,
        SchoolClass $schoolClass,
        CreateSectionAction $action,
    ): JsonResponse {
        return (new SectionResource($action->execute($schoolClass, $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Section $section): SectionResource
    {
        return new SectionResource($section->load('schoolClass'));
    }

    public function update(
        UpdateSectionRequest $request,
        Section $section,
        UpdateSectionAction $action,
    ): SectionResource {
        return new SectionResource($action->execute($section, $request->validated()));
    }

    public function destroy(Section $section, DeleteSectionAction $action): Response
    {
        $action->execute($section);

        return response()->noContent();
    }
}
