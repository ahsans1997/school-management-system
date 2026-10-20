<?php

namespace App\Http\Controllers;

use App\Actions\Fee\SaveFeeType;
use App\Http\Requests\Fee\FeeTypeRequest;
use App\Http\Resources\FeeTypeResource;
use App\Models\FeeType;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** The fee-type catalogue (Module 19). Read: fee.view; write: fee.create. */
class FeeTypeController extends Controller
{
    /** Paginated; ?active=1 for active types only. */
    public function index(): AnonymousResourceCollection
    {
        return FeeTypeResource::collection(FeeType::query()
            ->withCount('structures')
            ->when(request()->boolean('active'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate(PerPage::from(request())));
    }

    public function store(FeeTypeRequest $request, SaveFeeType $save): JsonResponse
    {
        return (new FeeTypeResource($save->execute($request->user(), null, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(FeeTypeRequest $request, FeeType $feeType, SaveFeeType $save): FeeTypeResource
    {
        return new FeeTypeResource($save->execute($request->user(), $feeType, $request->validated()));
    }

    public function destroy(FeeType $feeType, SaveFeeType $save): Response
    {
        $save->delete(request()->user(), $feeType);

        return response()->noContent();
    }
}
