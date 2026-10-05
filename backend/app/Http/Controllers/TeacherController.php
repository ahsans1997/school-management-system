<?php

namespace App\Http\Controllers;

use App\Actions\Person\CreatePersonAccountAction;
use App\Actions\Teacher\ArchiveTeacherAction;
use App\Actions\Teacher\CreateTeacherAction;
use App\Actions\Teacher\UpdateTeacherAction;
use App\Http\Requests\CreateAccountRequest;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TeacherController extends Controller
{
    /** Everything the profile shows. */
    private const PROFILE = [
        'account',
        'assignments.section.schoolClass',
        'assignments.classSubject.subject',
        'classTeacherOf.schoolClass',
        'documents',
    ];

    /**
     * ?search matches name, employee ID, phone or department;
     * ?archived=1 lists archived teachers instead; ?department filters.
     */
    public function index(): AnonymousResourceCollection
    {
        $teachers = Teacher::query()
            ->when(request()->boolean('archived'), fn ($query) => $query->onlyTrashed())
            ->with('account')
            ->withCount('assignments')
            ->when(request('search'), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->when(request('department'), fn ($query, string $department) => $query->where('department', $department))
            ->orderBy('name')
            ->paginate(PerPage::from(request()));

        return TeacherResource::collection($teachers);
    }

    public function store(StoreTeacherRequest $request, CreateTeacherAction $action): JsonResponse
    {
        return (new TeacherResource($action->execute($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    /** Archived teachers can still be viewed (read-only). */
    public function show(Teacher $teacher): TeacherResource
    {
        return new TeacherResource($teacher->load(self::PROFILE));
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher, UpdateTeacherAction $action): TeacherResource
    {
        return new TeacherResource($action->execute($teacher, $request->validated())->load(self::PROFILE));
    }

    /** Archive (soft delete); refused while the teacher still has classes. */
    public function destroy(Teacher $teacher, ArchiveTeacherAction $action): Response
    {
        $action->execute($teacher);

        return response()->noContent();
    }

    /** The login stays switched off; reactivate it under Users if they return. */
    public function restore(Teacher $teacher): TeacherResource
    {
        abort_unless($teacher->trashed(), 409, 'This teacher is not archived.');

        $teacher->restore();

        return new TeacherResource($teacher->load(self::PROFILE));
    }

    /** Give the teacher a Teacher-role login. */
    public function createAccount(CreateAccountRequest $request, Teacher $teacher, CreatePersonAccountAction $action): JsonResponse
    {
        $action->execute($teacher, 'Teacher', $request->validated());

        return (new TeacherResource($teacher->load(self::PROFILE)))
            ->response()
            ->setStatusCode(201);
    }
}
