<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ClassSubjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SchoolSettingsController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDocumentController;
use App\Http\Controllers\StudentGuardianController;
use App\Http\Controllers\StudentPhotoController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherFilesController;
use App\Http\Controllers\TeachingController;
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
| Private files via expiring signed links (App\Support\SignedFileUrl) — so
| <img> and download links work without a bearer token. The signature is the
| credential; links are only issued in responses to authorized callers.
*/
Route::middleware('signed:relative')->prefix('files')->group(function () {
    Route::get('students/{student}/photo', [PrivateFileController::class, 'studentPhoto'])->withTrashed()->name('files.student-photo');
    Route::get('teachers/{teacher}/photo', [PrivateFileController::class, 'teacherPhoto'])->withTrashed()->name('files.teacher-photo');
    Route::get('documents/{document}', [PrivateFileController::class, 'document'])->name('files.document');
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

    // Sections. Module 08. Listed/created under their class, then addressed directly.
    Route::get('classes/{school_class}/sections', [SectionController::class, 'index'])->middleware('permission:section.view');
    Route::post('classes/{school_class}/sections', [SectionController::class, 'store'])->middleware('permission:section.create');
    Route::get('sections/{section}', [SectionController::class, 'show'])->middleware('permission:section.view');
    Route::put('sections/{section}', [SectionController::class, 'update'])->middleware('permission:section.edit');
    Route::delete('sections/{section}', [SectionController::class, 'destroy'])->middleware('permission:section.delete');

    // Subjects. Module 09. The catalogue, then which classes teach each subject.
    Route::get('subjects', [SubjectController::class, 'index'])->middleware('permission:subject.view');
    Route::post('subjects', [SubjectController::class, 'store'])->middleware('permission:subject.create');
    Route::get('subjects/{subject}', [SubjectController::class, 'show'])->middleware('permission:subject.view');
    Route::put('subjects/{subject}', [SubjectController::class, 'update'])->middleware('permission:subject.edit');
    Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])->middleware('permission:subject.delete');
    Route::get('classes/{school_class}/subjects', [ClassSubjectController::class, 'index'])->middleware('permission:subject.view');
    Route::post('classes/{school_class}/subjects', [ClassSubjectController::class, 'store'])->middleware('permission:subject.edit');
    Route::delete('classes/{school_class}/subjects/{subject}', [ClassSubjectController::class, 'destroy'])->middleware('permission:subject.edit');

    // Guardians. Module 10. A login is created with guardian.edit (also checked
    // on POST /guardians when it includes one); links to students arrive in Module 11.
    Route::get('guardians', [GuardianController::class, 'index'])->middleware('permission:guardian.view');
    Route::post('guardians', [GuardianController::class, 'store'])->middleware('permission:guardian.create');
    Route::get('guardians/{guardian}', [GuardianController::class, 'show'])->middleware('permission:guardian.view');
    Route::put('guardians/{guardian}', [GuardianController::class, 'update'])->middleware('permission:guardian.edit');
    Route::delete('guardians/{guardian}', [GuardianController::class, 'destroy'])->middleware('permission:guardian.delete');
    Route::post('guardians/{guardian}/account', [GuardianController::class, 'createAccount'])->middleware('permission:guardian.edit');

    // Students. Module 11. Delete archives (soft delete); archived students stay
    // viewable but read-only (other routes don't bind trashed records → 404).
    Route::get('students', [StudentController::class, 'index'])->middleware('permission:student.view');
    Route::post('students', [StudentController::class, 'store'])->middleware('permission:student.create');
    Route::get('students/{student}', [StudentController::class, 'show'])->middleware('permission:student.view')->withTrashed();
    Route::put('students/{student}', [StudentController::class, 'update'])->middleware('permission:student.edit');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->middleware('permission:student.delete');
    Route::post('students/{student}/restore', [StudentController::class, 'restore'])->middleware('permission:student.delete')->withTrashed();
    Route::post('students/{student}/photo', [StudentPhotoController::class, 'store'])->middleware('permission:student.edit');
    Route::delete('students/{student}/photo', [StudentPhotoController::class, 'destroy'])->middleware('permission:student.edit');
    Route::post('students/{student}/documents', [StudentDocumentController::class, 'store'])->middleware('permission:student.edit');
    Route::delete('students/{student}/documents/{document}', [StudentDocumentController::class, 'destroy'])->middleware('permission:student.edit')->scopeBindings();
    Route::post('students/{student}/guardians', [StudentGuardianController::class, 'store'])->middleware('permission:student.edit');
    Route::put('students/{student}/guardians/{guardian}', [StudentGuardianController::class, 'update'])->middleware('permission:student.edit');
    Route::delete('students/{student}/guardians/{guardian}', [StudentGuardianController::class, 'destroy'])->middleware('permission:student.edit');

    // Teachers. Module 12. Delete archives (refused while they still teach);
    // archived teachers stay viewable but read-only.
    Route::get('teachers', [TeacherController::class, 'index'])->middleware('permission:teacher.view');
    Route::post('teachers', [TeacherController::class, 'store'])->middleware('permission:teacher.create');
    Route::get('teachers/{teacher}', [TeacherController::class, 'show'])->middleware('permission:teacher.view')->withTrashed();
    Route::put('teachers/{teacher}', [TeacherController::class, 'update'])->middleware('permission:teacher.edit');
    Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy'])->middleware('permission:teacher.delete');
    Route::post('teachers/{teacher}/restore', [TeacherController::class, 'restore'])->middleware('permission:teacher.delete')->withTrashed();
    Route::post('teachers/{teacher}/account', [TeacherController::class, 'createAccount'])->middleware('permission:teacher.edit');
    Route::post('teachers/{teacher}/photo', [TeacherFilesController::class, 'storePhoto'])->middleware('permission:teacher.edit');
    Route::delete('teachers/{teacher}/photo', [TeacherFilesController::class, 'destroyPhoto'])->middleware('permission:teacher.edit');
    Route::post('teachers/{teacher}/documents', [TeacherFilesController::class, 'storeDocument'])->middleware('permission:teacher.edit');
    Route::delete('teachers/{teacher}/documents/{document}', [TeacherFilesController::class, 'destroyDocument'])->middleware('permission:teacher.edit')->scopeBindings();
    Route::post('teachers/{teacher}/subjects', [TeachingController::class, 'storeSubject'])->middleware('permission:teacher.edit');
    Route::delete('teachers/{teacher}/subjects/{assignment}', [TeachingController::class, 'destroySubject'])->middleware('permission:teacher.edit')->scopeBindings();
    Route::post('teachers/{teacher}/classes', [TeachingController::class, 'storeClass'])->middleware('permission:teacher.edit');
    Route::delete('teachers/{teacher}/classes/{section}', [TeachingController::class, 'destroyClass'])->middleware('permission:teacher.edit');

    // Enrollments. Module 13. A student's section + roll for a year; the class
    // comes from the section. Leaving is a status change, not a delete.
    Route::get('enrollments', [EnrollmentController::class, 'index'])->middleware('permission:enrollment.view');
    Route::post('enrollments', [EnrollmentController::class, 'store'])->middleware('permission:enrollment.create');
    Route::post('enrollments/promote', [EnrollmentController::class, 'promote'])->middleware('permission:enrollment.create');
    Route::get('enrollments/{enrollment}', [EnrollmentController::class, 'show'])->middleware('permission:enrollment.view');
    Route::put('enrollments/{enrollment}', [EnrollmentController::class, 'update'])->middleware('permission:enrollment.edit');
    Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->middleware('permission:enrollment.delete');

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
