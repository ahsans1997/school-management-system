<?php

namespace App\Providers;

use App\Models\Guardian;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Short aliases for users.personable_type (User::PERSON_TYPES). Each
        // person module registers its model here when it lands.
        Relation::morphMap([
            'guardian' => Guardian::class,
        ]);

        // Brute-force protection on login: 5 attempts/min keyed by email + IP.
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by(mb_strtolower($email).'|'.$request->ip()),
            ];
        });
    }
}
