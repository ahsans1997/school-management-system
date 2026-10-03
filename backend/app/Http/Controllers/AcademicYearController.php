<?php

namespace App\Http\Controllers;

use App\Actions\AcademicYear\CreateAcademicYearAction;
use App\Actions\AcademicYear\DeleteAcademicYearAction;
use App\Actions\AcademicYear\SetCurrentAcademicYearAction;
use App\Actions\AcademicYear\UpdateAcademicYearAction;
use App\Http\Requests\AcademicYear\StoreAcademicYearRequest;
use App\Http\Requests\AcademicYear\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AcademicYearController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $years = AcademicYear::query()
            ->orderByDesc('start_date')
            ->paginate(request()->integer('per_page', 25));

        return AcademicYearResource::collection($years);
    }

    public function store(StoreAcademicYearRequest $request, CreateAcademicYearAction $action): JsonResponse
    {
        return (new AcademicYearResource($action->execute($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AcademicYear $academicYear): AcademicYearResource
    {
        return new AcademicYearResource($academicYear);
    }

    public function update(
        UpdateAcademicYearRequest $request,
        AcademicYear $academicYear,
        UpdateAcademicYearAction $action,
    ): AcademicYearResource {
        return new AcademicYearResource($action->execute($academicYear, $request->validated()));
    }

    public function destroy(AcademicYear $academicYear, DeleteAcademicYearAction $action): Response
    {
        $action->execute($academicYear);

        return response()->noContent();
    }

    public function setCurrent(AcademicYear $academicYear, SetCurrentAcademicYearAction $action): AcademicYearResource
    {
        return new AcademicYearResource($action->execute($academicYear));
    }
}
