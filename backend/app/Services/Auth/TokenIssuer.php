<?php

namespace App\Services\Auth;

use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class TokenIssuer
{
    /**
     * Issue a fresh access + refresh token pair for the user.
     *
     * Access tokens are short-lived (config jwt.ttl); refresh tokens are
     * long-lived (config jwt.refresh_ttl). Each carries a `type` claim so
     * routes can enforce which token is acceptable.
     *
     * Tokens are minted via the JWT manager (`fromUser`) rather than the
     * guard's `login()` so this never mutates the request's auth state.
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issue(User $user): array
    {
        $accessTtl = (int) config('jwt.ttl');
        $refreshTtl = (int) config('jwt.refresh_ttl');

        JWTAuth::factory()->setTTL($accessTtl);
        $accessToken = JWTAuth::claims(['type' => 'access'])->fromUser($user);

        JWTAuth::factory()->setTTL($refreshTtl);
        $refreshToken = JWTAuth::claims(['type' => 'refresh'])->fromUser($user);

        // Reset transient claim/TTL state on the manager.
        JWTAuth::claims([])->factory()->setTTL($accessTtl);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'expires_in' => $accessTtl * 60,
        ];
    }
}
