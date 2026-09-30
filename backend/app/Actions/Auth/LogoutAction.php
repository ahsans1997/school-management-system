<?php

namespace App\Actions\Auth;

class LogoutAction
{
    /**
     * Invalidate (blacklist) the access token presented on the request.
     */
    public function execute(): void
    {
        auth('api')->logout();
    }
}
