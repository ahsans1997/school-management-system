<?php

namespace App\Http\Controllers;

use App\Actions\Role\CreateRoleAction;
use App\Actions\Role\DeleteRoleAction;
use App\Actions\Role\SyncRolePermissionsAction;
use App\Actions\Role\UpdateRoleAction;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\SyncRolePermissionsRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->paginate(request()->integer('per_page', 25));

        return RoleResource::collection($roles);
    }

    public function store(StoreRoleRequest $request, CreateRoleAction $action): JsonResponse
    {
        $role = $action->execute(
            $request->validated('name'),
            $request->validated('permissions', []),
        );

        return (new RoleResource($role))->response()->setStatusCode(201);
    }

    public function show(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions'));
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRoleAction $action): RoleResource
    {
        return new RoleResource($action->execute($role, $request->validated('name')));
    }

    public function destroy(Role $role, DeleteRoleAction $action): Response
    {
        $action->execute($role);

        return response()->noContent();
    }

    public function syncPermissions(
        SyncRolePermissionsRequest $request,
        Role $role,
        SyncRolePermissionsAction $action,
    ): RoleResource {
        return new RoleResource($action->execute($role, $request->validated('permissions')));
    }
}
