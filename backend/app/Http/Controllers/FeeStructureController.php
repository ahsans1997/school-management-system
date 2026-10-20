<?php

namespace App\Http\Controllers;

use App\Actions\Fee\SaveFeeStructure;
use App\Http\Requests\Fee\FeeStructureRequest;
use App\Http\Resources\FeeStructureResource;
use App\Models\FeeStructure;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * What each class pays per fee type and year (Module 19). Read: fee.view;
 * write: fee.create. Changes are audited.
 */
class FeeStructureController extends Controller
{
    /** Paginated; ?academic_year_id, ?class_id. */
    public function index(): AnonymousResourceCollection
    {
        request()->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')],
        ]);

        return FeeStructureResource::collection(FeeStructure::query()
            ->with(['feeType', 'schoolClass', 'academicYear'])
            ->when(request()->integer('academic_year_id'), fn ($q, int $id) => $q->where('academic_year_id', $id))
            ->when(request()->integer('class_id'), fn ($q, int $id) => $q->where('class_id', $id))
            ->join('classes', 'classes.id', '=', 'fee_structures.class_id')
            ->join('fee_types', 'fee_types.id', '=', 'fee_structures.fee_type_id')
            ->select('fee_structures.*')
            ->orderBy('classes.numeric_level')
            ->orderBy('fee_types.name')
            ->paginate(PerPage::from(request())));
    }

    public function store(FeeStructureRequest $request, SaveFeeStructure $save): JsonResponse
    {
        $structure = $save->execute($request->user(), null, $request->validated());

        return (new FeeStructureResource($structure->load(['feeType', 'schoolClass', 'academicYear'])))->response()->setStatusCode(201);
    }

    public function update(FeeStructureRequest $request, FeeStructure $feeStructure, SaveFeeStructure $save): FeeStructureResource
    {
        return new FeeStructureResource($save->execute($request->user(), $feeStructure, $request->validated())
            ->load(['feeType', 'schoolClass', 'academicYear']));
    }

    public function destroy(FeeStructure $feeStructure, SaveFeeStructure $save): Response
    {
        $save->delete(request()->user(), $feeStructure);

        return response()->noContent();
    }
}
