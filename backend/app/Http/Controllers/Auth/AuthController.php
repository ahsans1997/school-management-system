<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\RefreshTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $validated = $request->validated();

        return $this->tokenResponse(
            $action->execute($validated['email'], $validated['password'])
        );
    }

    public function refresh(RefreshTokenAction $action): JsonResponse
    {
        return $this->tokenResponse($action->execute());
    }

    public function logout(LogoutAction $action): JsonResponse
    {
        $action->execute();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        return response()->json(['user' => new UserResource($user)]);
    }

    /**
     * @param  array{user: User, access_token: string, refresh_token: string, token_type: string, expires_in: int}  $result
     */
    private function tokenResponse(array $result): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
        ]);
    }
}
