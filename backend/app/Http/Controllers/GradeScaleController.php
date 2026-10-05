<?php

namespace App\Http\Controllers;

use App\Http\Requests\Exam\SaveGradeScaleRequest;
use App\Http\Resources\GradeScaleResource;
use App\Models\GradeScale;
use App\Services\Exam\GradeScaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Grade scales. Anyone with exam.view can read them; changing one needs
 * exam.grading (Admin only by default) because it regrades every result.
 */
class GradeScaleController extends Controller
{
    public function __construct(private readonly GradeScaleService $scales) {}

    /** Every scale with its bands. A school only ever has a handful. */
    public function index(): AnonymousResourceCollection
    {
        return GradeScaleResource::collection(
            GradeScale::query()->with('grades')->withCount('exams')->orderBy('name')->get(),
        );
    }

    public function store(SaveGradeScaleRequest $request): JsonResponse
    {
        return (new GradeScaleResource($this->scales->save(null, $request->validated())->loadCount('exams')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(GradeScale $gradeScale): GradeScaleResource
    {
        return new GradeScaleResource($gradeScale->load('grades')->loadCount('exams'));
    }

    public function update(SaveGradeScaleRequest $request, GradeScale $gradeScale): GradeScaleResource
    {
        return new GradeScaleResource($this->scales->save($gradeScale, $request->validated())->loadCount('exams'));
    }

    public function destroy(GradeScale $gradeScale): Response
    {
        $this->scales->delete($gradeScale);

        return response()->noContent();
    }
}
