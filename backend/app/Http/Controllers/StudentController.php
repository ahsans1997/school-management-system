<?php

namespace App\Http\Controllers;

use App\Actions\Student\CreateStudentAction;
use App\Actions\Student\UpdateStudentAction;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StudentController extends Controller
{
    /** Loaded for a profile. */
    private const PROFILE = [
        'guardians',
        'documents',
        'currentEnrollment.schoolClass',
        'currentEnrollment.section',
        'currentEnrollment.academicYear',
        'enrollments.schoolClass',
        'enrollments.section',
        'enrollments.academicYear',
    ];

    /**
     * ?search matches name, student ID, admission no. or phone;
     * ?archived=1 lists archived students instead; ?gender filters;
     * ?class_id / ?section_id filter by this year's enrollment.
     * Teachers only ever see the students of their own sections.
     */
    public function index(): AnonymousResourceCollection
    {
        $students = Student::query()
            ->visibleTo(request()->user())
            ->when(request()->boolean('archived'), fn ($query) => $query->onlyTrashed())
            ->with(['primaryGuardian', 'currentEnrollment.schoolClass', 'currentEnrollment.section'])
            ->when(request()->integer('class_id'), fn ($query, int $id) => $query->whereHas('currentEnrollment', fn ($q) => $q->where('class_id', $id)))
            ->when(request()->integer('section_id'), fn ($query, int $id) => $query->whereHas('currentEnrollment', fn ($q) => $q->where('section_id', $id)))
            ->when(request('search'), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('admission_no', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(request('gender'), fn ($query, string $gender) => $query->where('gender', $gender))
            ->orderBy('name')
            ->paginate(PerPage::from(request()));

        return StudentResource::collection($students);
    }

    public function store(StoreStudentRequest $request, CreateStudentAction $action): JsonResponse
    {
        return (new StudentResource($action->execute($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Archived students can still be viewed (read-only). Outside a teacher's
     * own sections the student is reported missing, not forbidden.
     */
    public function show(Student $student): StudentResource
    {
        abort_unless(
            Student::withTrashed()->visibleTo(request()->user())->whereKey($student->id)->exists(),
            404,
        );

        return new StudentResource($student->load(self::PROFILE));
    }

    public function update(UpdateStudentRequest $request, Student $student, UpdateStudentAction $action): StudentResource
    {
        return new StudentResource($action->execute($student, $request->validated())->load(self::PROFILE));
    }

    /** Archive (soft delete) — student records are never hard-deleted. */
    public function destroy(Student $student): Response
    {
        $student->delete();

        return response()->noContent();
    }

    public function restore(Student $student): StudentResource
    {
        abort_unless($student->trashed(), 409, 'This student is not archived.');

        $student->restore();

        return new StudentResource($student->load(self::PROFILE));
    }
}
