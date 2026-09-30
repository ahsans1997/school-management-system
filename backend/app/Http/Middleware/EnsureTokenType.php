<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenType
{
    /**
     * Ensure the presented JWT is of the expected type (access | refresh).
     * Prevents a refresh token being used on access-only routes and vice versa.
     * Must run after the `auth:api` middleware.
     */
    public function handle(Request $request, Closure $next, string $type): Response
    {
        if (auth('api')->payload()->get('type') !== $type) {
            throw new AuthenticationException('Invalid token type for this endpoint.');
        }

        return $next($request);
    }
}
