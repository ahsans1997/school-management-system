<?php

namespace App\Http\Controllers;

use App\Actions\User\AssignRoleAction;
use App\Actions\User\CreateUserAction;
use App\Actions\User\DeleteUserAction;
use App\Actions\User\ResetPasswordAction;
use App\Actions\User\ToggleStatusAction;
use App\Actions\User\UpdateUserAction;
use App\Http\Requests\User\AssignRoleRequest;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\ManagedUserResource;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles')
            ->when(request('search'), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(request('role'), function ($query, string $role) {
                $query->whereHas('roles', fn ($q) => $q->where('name', $role));
            })
            ->when(request()->has('is_active'), function ($query) {
                $query->where('is_active', request()->boolean('is_active'));
            })
            ->orderBy('name')
            ->paginate(PerPage::from(request()));

        return ManagedUserResource::collection($users);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $user = $action->execute($request->validated());

        return (new ManagedUserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): ManagedUserResource
    {
        return new ManagedUserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): ManagedUserResource
    {
        return new ManagedUserResource($action->execute($user, $request->validated()));
    }

    public function destroy(User $user, DeleteUserAction $action): Response
    {
        Gate::authorize('delete', $user);
        $action->execute($user);

        return response()->noContent();
    }

    public function assignRole(AssignRoleRequest $request, User $user, AssignRoleAction $action): ManagedUserResource
    {
        Gate::authorize('assignRole', $user);

        return new ManagedUserResource($action->execute($user, $request->validated('roles')));
    }

    public function toggleStatus(User $user, ToggleStatusAction $action): ManagedUserResource
    {
        Gate::authorize('toggleStatus', $user);

        return new ManagedUserResource($action->execute($user));
    }

    public function resetPassword(ResetPasswordRequest $request, User $user, ResetPasswordAction $action): JsonResponse
    {
        $result = $action->execute($user, $request->validated('password'));

        return response()->json(array_filter([
            'message' => 'Password reset.',
            'password' => $result['password'],
        ], fn ($value) => $value !== null));
    }
}
