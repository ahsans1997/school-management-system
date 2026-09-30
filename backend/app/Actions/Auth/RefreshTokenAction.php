<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Auth\TokenIssuer;

class RefreshTokenAction
{
    public function __construct(private readonly TokenIssuer $tokens) {}

    /**
     * Rotate the presented refresh token: blacklist it, then issue a new
     * access + refresh pair for the same user. The route guarantees the
     * caller presented a valid `type=refresh` token.
     *
     * @return array{user: User, access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function execute(): array
    {
        /** @var User $user */
        $user = auth('api')->user();

        // Invalidate the refresh token that was just used (rotation).
        auth('api')->logout();

        return ['user' => $user, ...$this->tokens->issue($user)];
    }
}
