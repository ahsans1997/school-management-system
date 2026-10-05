<?php

namespace App\Http\Controllers;

use App\Actions\Guardian\CreateGuardianAccountAction;
use App\Actions\Guardian\CreateGuardianAction;
use App\Actions\Guardian\DeleteGuardianAction;
use App\Actions\Guardian\UpdateGuardianAction;
use App\Http\Requests\Guardian\CreateGuardianAccountRequest;
use App\Http\Requests\Guardian\StoreGuardianRequest;
use App\Http\Requests\Guardian\UpdateGuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GuardianController extends Controller
{
    /** ?search matches name, phone or email — e.g. a lookup by phone at the front desk. */
    public function index(): AnonymousResourceCollection
    {
        $guardians = Guardian::query()
            ->with('account')
            ->withCount('students')
            ->when(request('search'), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(PerPage::from(request()));

        return GuardianResource::collection($guardians);
    }

    public function store(StoreGuardianRequest $request, CreateGuardianAction $action): JsonResponse
    {
        return (new GuardianResource($action->execute($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Guardian $guardian): GuardianResource
    {
        return new GuardianResource($guardian->load(['account', 'students']));
    }

    public function update(
        UpdateGuardianRequest $request,
        Guardian $guardian,
        UpdateGuardianAction $action,
    ): GuardianResource {
        return new GuardianResource($action->execute($guardian, $request->validated())->load('account'));
    }

    public function destroy(Guardian $guardian, DeleteGuardianAction $action): Response
    {
        $action->execute($guardian);

        return response()->noContent();
    }

    /** Give an existing guardian a Parent-portal login. */
    public function createAccount(
        CreateGuardianAccountRequest $request,
        Guardian $guardian,
        CreateGuardianAccountAction $action,
    ): JsonResponse {
        $action->execute($guardian, $request->validated());

        return (new GuardianResource($guardian))
            ->response()
            ->setStatusCode(201);
    }
}
