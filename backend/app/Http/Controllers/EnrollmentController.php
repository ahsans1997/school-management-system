<?php

namespace App\Http\Controllers;

use App\Actions\Enrollment\Enrollments;
use App\Enums\EnrollmentStatus;
use App\Http\Requests\Enrollment\PromoteRequest;
use App\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Http\Requests\Enrollment\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\GuardedDelete;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EnrollmentController extends Controller
{
    private const WITH = ['student', 'academicYear', 'schoolClass', 'section'];

    /**
     * Filters: ?academic_year_id (default: the current year), ?class_id,
     * ?section_id, ?status, ?search (student name or ID). Ordered by class
     * level, section, roll. With a section filter the response also carries
     * that section's seats (capacity / taken) for the year.
     */
    public function index(): AnonymousResourceCollection
    {
        $yearId = request()->integer('academic_year_id') ?: AcademicYear::current()?->id;

        $enrollments = StudentEnrollment::query()
            ->with(self::WITH)
            ->where('student_enrollments.academic_year_id', $yearId ?? 0)
            ->when(request()->integer('class_id'), fn ($q, int $id) => $q->where('student_enrollments.class_id', $id))
            ->when(request()->integer('section_id'), fn ($q, int $id) => $q->where('student_enrollments.section_id', $id))
            ->when(request('status'), fn ($q, string $status) => $q->where('student_enrollments.status', $status))
            ->when(request('search'), function ($q, string $search) {
                $q->whereHas('student', fn ($s) => $s->where(function ($s) use ($search) {
                    $s->where('name', 'like', "%{$search}%")->orWhere('student_id', 'like', "%{$search}%");
                }));
            })
            ->join('classes', 'classes.id', '=', 'student_enrollments.class_id')
            ->join('sections', 'sections.id', '=', 'student_enrollments.section_id')
            ->orderBy('classes.numeric_level')
            ->orderBy('sections.name')
            ->orderBy('student_enrollments.roll')
            ->select('student_enrollments.*')
            ->paginate(PerPage::from(request()));

        $collection = EnrollmentResource::collection($enrollments)
            ->additional(['academic_year_id' => $yearId]);

        $section = request()->integer('section_id') ? Section::find(request()->integer('section_id')) : null;
        $year = $yearId ? AcademicYear::find($yearId) : null;
        if ($section !== null && $year !== null) {
            $collection->additional([
                'academic_year_id' => $yearId,
                'seats' => ['capacity' => $section->capacity, 'taken' => Enrollments::seatsTaken($section, $year)],
            ]);
        }

        return $collection;
    }

    public function store(StoreEnrollmentRequest $request, Enrollments $enrollments): JsonResponse
    {
        $enrollment = $enrollments->enroll(
            Student::findOrFail($request->integer('student_id')),
            AcademicYear::findOrFail($request->integer('academic_year_id')),
            Section::findOrFail($request->integer('section_id')),
            $request->filled('roll') ? $request->integer('roll') : null,
        );

        return (new EnrollmentResource($enrollment->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(StudentEnrollment $enrollment): EnrollmentResource
    {
        return new EnrollmentResource($enrollment->load(self::WITH));
    }

    public function update(UpdateEnrollmentRequest $request, StudentEnrollment $enrollment, Enrollments $enrollments): EnrollmentResource
    {
        $enrollment = $enrollments->update(
            $enrollment,
            Section::findOrFail($request->integer('section_id')),
            $request->filled('roll') ? $request->integer('roll') : null,
            EnrollmentStatus::from($request->string('status')->toString()),
            $request->input('ended_on'),
            $request->input('exit_reason'),
        );

        return new EnrollmentResource($enrollment->load(self::WITH));
    }

    /** For mistakes. Leaving the school is a status change, not a delete. */
    public function destroy(StudentEnrollment $enrollment): Response
    {
        GuardedDelete::delete($enrollment, 'This enrollment is in use (for example by attendance or results) and cannot be deleted. Change its status instead.');

        return response()->noContent();
    }

    public function promote(PromoteRequest $request, Enrollments $enrollments): JsonResponse
    {
        $created = $enrollments->promote(
            AcademicYear::findOrFail($request->integer('from_year_id')),
            Section::findOrFail($request->integer('from_section_id')),
            AcademicYear::findOrFail($request->integer('to_year_id')),
            Section::findOrFail($request->integer('to_section_id')),
            array_map('intval', $request->input('student_ids')),
        );

        return EnrollmentResource::collection(
            StudentEnrollment::query()->with(self::WITH)->whereKey(array_map(fn ($e) => $e->id, $created))->orderBy('roll')->get()
        )->additional(['promoted' => count($created)])->response()->setStatusCode(201);
    }
}
