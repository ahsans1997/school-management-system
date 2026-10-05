<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\SaveAttendanceSheet;
use App\Enums\EnrollmentStatus;
use App\Http\Requests\Attendance\SaveAttendanceRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceDetail;
use App\Models\Section;
use App\Models\Student;
use App\Services\Attendance\AttendanceService;
use App\Support\PerPage;
use App\Support\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * Student attendance. Two layers on every endpoint (spec §52): the
 * attendance.* permission (route middleware) AND TeacherScope — a teacher
 * works only with the sections they teach or are class teacher of.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /** Sections the caller may take attendance for this year, with today's state. */
    public function sections(): JsonResponse
    {
        $ids = TeacherScope::sectionIds(request()->user());
        $year = AcademicYear::current();
        $today = today()->toDateString();

        $sections = Section::query()
            ->with('schoolClass')
            ->when($ids !== null, fn ($q) => $q->whereKey($ids))
            ->withCount(['enrollments as students' => fn ($q) => $q->where('academic_year_id', $year?->id ?? 0)
                ->where('status', EnrollmentStatus::Active->value)
                ->whereHas('student', fn ($s) => $s->withoutTrashed())])
            ->withExists(['attendances as marked_today' => fn ($q) => $q->whereDate('date', $today)])
            ->get()
            ->sortBy(fn (Section $s) => [$s->schoolClass->numeric_level, $s->name])
            ->values();

        return response()->json(['data' => $sections->map(fn (Section $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'class' => ['id' => $s->schoolClass->id, 'name' => $s->schoolClass->name],
            'students' => $s->students,
            'marked_today' => (bool) $s->marked_today,
        ])]);
    }

    /** The sheet to fill in: the day's roll with any statuses already saved. */
    public function sheet(): JsonResponse
    {
        request()->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $section = Section::with('schoolClass')->findOrFail(request()->integer('section_id'));
        TeacherScope::authorize(request()->user(), $section);
        $date = request()->string('date')->toString();

        $year = $this->attendance->yearFor($date);
        abort_if($year === null, 422, "No academic year covers {$date}.");

        $sheet = Attendance::query()->with(['takenBy', 'updatedBy'])
            ->where('section_id', $section->id)->whereDate('date', $date)->first();
        $saved = $sheet?->details()->with('student')->get()->keyBy('student_id') ?? collect();
        $roster = $this->attendance->roster($section, $year, $date);

        $rows = $roster->map(fn ($e) => [
            'student_id' => $e->student_id,
            'name' => $e->student->name,
            'roll' => $e->roll,
            'status' => $saved->get($e->student_id)?->status->value,
            'remark' => $saved->get($e->student_id)?->remark,
        ]);
        // Rows already saved for students no longer on the roll (e.g. archived since).
        $extra = $saved->except($roster->pluck('student_id')->all())->map(fn (AttendanceDetail $d) => [
            'student_id' => $d->student_id,
            'name' => $d->student->name,
            'roll' => null,
            'status' => $d->status->value,
            'remark' => $d->remark,
        ]);

        return response()->json(['data' => [
            'section' => ['id' => $section->id, 'name' => $section->name, 'class' => $section->schoolClass->name],
            'date' => $date,
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'attendance' => $sheet ? [
                'id' => $sheet->id,
                'taken_by' => $sheet->takenBy?->name,
                'updated_by' => $sheet->updatedBy?->name,
                'updated_at' => $sheet->updated_at?->toIso8601String(),
            ] : null,
            'students' => $rows->concat($extra->values())->values(),
        ]]);
    }

    /** Sheets taken, newest first, with counts. ?section_id, ?class_id, ?from, ?to. */
    public function index(): JsonResponse
    {
        $ids = TeacherScope::sectionIds(request()->user());

        $sheets = Attendance::query()
            ->with(['schoolClass', 'section', 'takenBy'])
            ->withCount([
                'details as present' => fn ($q) => $q->where('status', 'present'),
                'details as absent' => fn ($q) => $q->where('status', 'absent'),
                'details as late' => fn ($q) => $q->where('status', 'late'),
                'details as leave' => fn ($q) => $q->where('status', 'leave'),
            ])
            ->when($ids !== null, fn ($q) => $q->whereIn('section_id', $ids))
            ->when(request()->integer('section_id'), fn ($q, int $id) => $q->where('section_id', $id))
            ->when(request()->integer('class_id'), fn ($q, int $id) => $q->where('class_id', $id))
            ->when(request('from'), fn ($q, string $from) => $q->whereDate('date', '>=', $from))
            ->when(request('to'), fn ($q, string $to) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->paginate(PerPage::from(request()));

        return response()->json([
            'data' => collect($sheets->items())->map(fn (Attendance $a) => [
                'id' => $a->id,
                'date' => $a->date->toDateString(),
                'class' => ['id' => $a->schoolClass->id, 'name' => $a->schoolClass->name],
                'section' => ['id' => $a->section->id, 'name' => $a->section->name],
                'taken_by' => $a->takenBy?->name,
                'counts' => ['present' => $a->present, 'absent' => $a->absent, 'late' => $a->late, 'leave' => $a->leave],
            ]),
            'meta' => [
                'current_page' => $sheets->currentPage(),
                'last_page' => $sheets->lastPage(),
                'per_page' => $sheets->perPage(),
                'total' => $sheets->total(),
            ],
        ]);
    }

    /** Take (or re-save) a section's sheet for a day in one request. */
    public function store(SaveAttendanceRequest $request, SaveAttendanceSheet $save): JsonResponse
    {
        $section = Section::findOrFail($request->integer('section_id'));
        TeacherScope::authorize($request->user(), $section);

        [$sheet, $created] = $save->execute($request->user(), $section, $request->string('date')->toString(), $request->input('records'));

        return $this->saved($sheet)->setStatusCode($created ? 201 : 200);
    }

    /** Edit a sheet by id (same rules as re-saving it). */
    public function update(SaveAttendanceRequest $request, Attendance $attendance, SaveAttendanceSheet $save): JsonResponse
    {
        TeacherScope::authorize($request->user(), $attendance->section);

        [$sheet] = $save->execute($request->user(), $attendance->section, $attendance->date->toDateString(), $request->input('records'));

        return $this->saved($sheet);
    }

    /** ?section_id & ?month=YYYY-MM → day grid + per-student summary. */
    public function monthly(): JsonResponse
    {
        request()->validate([
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $section = Section::with('schoolClass')->findOrFail(request()->integer('section_id'));
        TeacherScope::authorize(request()->user(), $section);

        return response()->json(['data' => [
            'section' => ['id' => $section->id, 'name' => $section->name, 'class' => $section->schoolClass->name],
            ...$this->attendance->monthly($section, request()->string('month')->toString()),
        ]]);
    }

    /** One student's year (?academic_year_id, default current). Teachers: own sections only (404). */
    public function student(Student $student): JsonResponse
    {
        abort_unless(
            Student::withTrashed()->visibleTo(request()->user())->whereKey($student->id)->exists(),
            404,
        );
        $year = request()->integer('academic_year_id')
            ? AcademicYear::findOrFail(request()->integer('academic_year_id'))
            : AcademicYear::current();
        abort_if($year === null, 422, 'There is no current academic year.');

        return response()->json(['data' => $this->attendance->forStudent($student, $year)]);
    }

    private function saved(Attendance $sheet): JsonResponse
    {
        $sheet->load(['schoolClass', 'section']);

        return response()->json(['data' => [
            'id' => $sheet->id,
            'date' => $sheet->date->toDateString(),
            'class' => $sheet->schoolClass->name,
            'section' => $sheet->section->name,
            'counts' => collect($this->attendance->summarize($sheet->details()->get()))->only(['present', 'absent', 'late', 'leave']),
        ]]);
    }
}
