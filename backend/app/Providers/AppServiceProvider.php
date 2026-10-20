<?php

namespace App\Providers;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Guardian;
use App\Models\Mark;
use App\Models\Result;
use App\Models\Student;
use App\Models\Teacher;
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
        // Short aliases for users.personable_type (User::PERSON_TYPES) — each
        // person module registers its model here when it lands — and for
        // audit_logs.auditable_type.
        Relation::morphMap([
            'guardian' => Guardian::class,
            'student' => Student::class,
            'teacher' => Teacher::class,
            'exam' => Exam::class,
            'exam_subject' => ExamSubject::class,
            'mark' => Mark::class,
            'result' => Result::class,
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
