<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolSettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  (base prefix: /api, no version segment)
|--------------------------------------------------------------------------
| Registered in bootstrap/app.php via withRouting(api: ...). Feature
| modules add their route groups inside the authenticated group below.
*/

// Public health/liveness probe.
Route::get('/health', HealthController::class);

// Authentication (JWT). Module 01.
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->middleware('token.type:access');
        Route::post('logout', [AuthController::class, 'logout'])->middleware('token.type:access');
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('token.type:refresh');
    });
});

/*
| Authenticated API — access tokens only. Every protected feature route
| lives here; refresh tokens are rejected by token.type:access.
*/
Route::middleware(['auth:api', 'token.type:access', 'active'])->group(function () {
    // Dashboard. Module 05. Authenticated only — widgets gate themselves.
    Route::get('dashboard', DashboardController::class);

    // Academic Years. Module 06.
    Route::get('academic-years', [AcademicYearController::class, 'index'])->middleware('permission:academic_year.view');
    Route::post('academic-years', [AcademicYearController::class, 'store'])->middleware('permission:academic_year.create');
    Route::get('academic-years/{academic_year}', [AcademicYearController::class, 'show'])->middleware('permission:academic_year.view');
    Route::put('academic-years/{academic_year}', [AcademicYearController::class, 'update'])->middleware('permission:academic_year.edit');
    Route::delete('academic-years/{academic_year}', [AcademicYearController::class, 'destroy'])->middleware('permission:academic_year.delete');
    Route::post('academic-years/{academic_year}/set-current', [AcademicYearController::class, 'setCurrent'])->middleware('permission:academic_year.edit');

    // Classes. Module 07.
    Route::get('classes', [SchoolClassController::class, 'index'])->middleware('permission:class.view');
    Route::post('classes', [SchoolClassController::class, 'store'])->middleware('permission:class.create');
    Route::get('classes/{school_class}', [SchoolClassController::class, 'show'])->middleware('permission:class.view');
    Route::put('classes/{school_class}', [SchoolClassController::class, 'update'])->middleware('permission:class.edit');
    Route::delete('classes/{school_class}', [SchoolClassController::class, 'destroy'])->middleware('permission:class.delete');

    // School Settings. Module 04.
    Route::get('settings', [SchoolSettingsController::class, 'show'])->middleware('permission:settings.view');
    Route::put('settings', [SchoolSettingsController::class, 'update'])->middleware('permission:settings.manage');
    Route::post('settings/logo', [SchoolSettingsController::class, 'uploadLogo'])->middleware('permission:settings.manage');
    Route::post('settings/favicon', [SchoolSettingsController::class, 'uploadFavicon'])->middleware('permission:settings.manage');

    // Users. Module 03.
    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.view');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.view');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:user.edit');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');
    Route::post('users/{user}/assign-role', [UserController::class, 'assignRole'])->middleware('permission:user.edit');
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:user.edit');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:user.edit');

    // Roles & Permissions. Module 02.
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:role.view');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:role.view');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.edit');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');
    Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:role.edit');

    Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:permission.view');
});
