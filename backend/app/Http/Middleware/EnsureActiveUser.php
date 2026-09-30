<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Reject requests from a deactivated account, even with a valid token,
     * so deactivation takes effect immediately. Runs after `auth:api`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if ($user && ! $user->is_active) {
            abort(403, 'This account has been disabled.');
        }

        return $next($request);
    }
}
