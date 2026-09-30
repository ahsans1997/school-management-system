<?php

namespace Tests;

use App\Models\User;
use App\Services\Auth\TokenIssuer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate subsequent requests as $user with a real access token.
     *
     * Feature tests reuse one application across requests, so per-request
     * auth state must be reset to mimic a fresh request:
     *  - the JWT manager (`tymon.jwt`, a *scoped* binding) memoizes the token
     *    it parsed and never re-reads a new Authorization header, and
     *  - the guard caches both that manager and the user it resolved.
     * Without this, a second request as a different user is served as the
     * first. Production is unaffected: one request per process, and Octane
     * flushes scoped instances itself.
     */
    protected function asUser(User $user): static
    {
        $this->app->forgetScopedInstances();
        $this->app['auth']->forgetGuards();

        return $this->withToken(app(TokenIssuer::class)->issue($user)['access_token']);
    }
}
