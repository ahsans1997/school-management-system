<?php

namespace App\Http\Controllers;

use App\Actions\Routine\SaveRoutine;
use App\Enums\DashboardAudience;
use App\Http\Requests\Routine\SaveRoutineRequest;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Routine;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Routine\RoutineService;
use App\Support\TeacherScope;
use Illuminate\Http\JsonResponse;

/**
 * Class routines (spec §15). Editing needs routine.create/edit; viewing
 * needs routine.view, and teachers only see their own sections and their
 * own teacher routine. GET /routines/me serves each portal its own view.
 */
class RoutineController extends Controller
{
    public function __construct(private readonly RoutineService $routines) {}

    /** Routines of a year (default current) with slot counts. */
    public function index(): JsonResponse
    {
        $year = $this->year();
        $ids = TeacherScope::sectionIds(request()->user());

        $list = Routine::query()
            ->with('section.schoolClass')
            ->withCount('slots')
            ->where('academic_year_id', $year->id)
            ->when($ids !== null, fn ($q) => $q->whereIn('section_id', $ids))
            ->get()
            ->sortBy(fn (Routine $r) => [$r->section->schoolClass->numeric_level, $r->section->name])
            ->values();

        return response()->json([
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'data' => $list->map(fn (Routine $r) => [
                'id' => $r->id,
                'class' => ['id' => $r->section->schoolClass->id, 'name' => $r->section->schoolClass->name],
                'section' => ['id' => $r->section->id, 'name' => $r->section->name],
                'slots' => $r->slots_count,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    /** A section's routine (class view) + the subject/teacher options for the builder. */
    public function forSection(Section $section): JsonResponse
    {
        TeacherScope::authorize(request()->user(), $section);
        $year = $this->year();

        return response()->json(['data' => [
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'section' => ['id' => $section->id, 'name' => $section->name, 'class' => $section->schoolClass->name],
            ...$this->routines->forSection($section, $year),
        ]]);
    }

    /** Create or replace a section's week. Replacing an existing routine also needs routine.edit. */
    public function store(SaveRoutineRequest $request, SaveRoutine $save): JsonResponse
    {
        $section = Section::findOrFail($request->integer('section_id'));
        $year = AcademicYear::findOrFail($request->integer('academic_year_id'));
        $exists = Routine::query()->where('section_id', $section->id)->where('academic_year_id', $year->id)->exists();
        abort_if($exists && ! $request->user()->can('routine.edit'), 403, 'This section already has a routine; changing it needs edit permission.');

        $save->execute($section, $year, $request->input('slots'));

        return $this->sectionView($section, $year)->setStatusCode($exists ? 200 : 201);
    }

    public function update(SaveRoutineRequest $request, Routine $routine, SaveRoutine $save): JsonResponse
    {
        $save->execute($routine->section, $routine->academicYear, $request->input('slots'));

        return $this->sectionView($routine->section, $routine->academicYear);
    }

    /** A teacher's week across sections. Teachers may only see their own. */
    public function teacher(Teacher $teacher): JsonResponse
    {
        $user = request()->user();
        if (TeacherScope::sectionIds($user) !== null) {
            abort_unless(Teacher::forUser($user)?->is($teacher), 403, 'You can only see your own routine.');
        }
        $year = $this->year();

        return response()->json(['data' => [
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'teacher' => ['id' => $teacher->id, 'name' => $teacher->name],
            'slots' => $this->routines->forTeacher($teacher, $year),
        ]]);
    }

    /** Who uses a room when (?room=101). */
    public function room(): JsonResponse
    {
        request()->validate(['room' => ['required', 'string', 'max:50']]);
        $year = $this->year();

        return response()->json(['data' => [
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'room' => trim(request()->string('room')),
            'slots' => $this->routines->forRoom(request()->string('room')->toString(), $year),
        ]]);
    }

    /**
     * The caller's own routine for the current year, by who they are:
     * teacher → their teaching week; student → their section; parent →
     * each child's section. Ownership decides, so no routine.* permission.
     */
    public function me(): JsonResponse
    {
        $user = request()->user();
        $year = AcademicYear::current();
        abort_if($year === null, 422, 'There is no current academic year.');

        $sectionWeek = function (Student $student) use ($year) {
            $enrollment = $student->enrollments()->where('academic_year_id', $year->id)->active()->with('section.schoolClass')->first();

            return [
                'student' => ['id' => $student->id, 'name' => $student->name],
                'section' => $enrollment ? ['id' => $enrollment->section->id, 'name' => $enrollment->section->name, 'class' => $enrollment->section->schoolClass->name] : null,
                'slots' => $enrollment ? $this->routines->forSection($enrollment->section, $year)['slots'] : [],
            ];
        };

        $data = match (DashboardAudience::forUser($user)) {
            DashboardAudience::Teacher => ($t = Teacher::forUser($user))
                ? ['type' => 'teacher', 'teacher' => ['id' => $t->id, 'name' => $t->name], 'slots' => $this->routines->forTeacher($t, $year)]
                : ['type' => 'teacher', 'teacher' => null, 'slots' => []],
            DashboardAudience::Student => ['type' => 'student', 'weeks' => ($s = $this->personOf($user, Student::class)) ? [$sectionWeek($s)] : []],
            DashboardAudience::Parent => ['type' => 'parent', 'weeks' => ($g = $this->personOf($user, Guardian::class))
                ? $g->students()->orderBy('name')->get()->map($sectionWeek)->all()
                : []],
            default => ['type' => 'none'],
        };

        return response()->json(['data' => ['academic_year' => ['id' => $year->id, 'name' => $year->name], ...$data]]);
    }

    private function sectionView(Section $section, AcademicYear $year): JsonResponse
    {
        return response()->json(['data' => [
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'section' => ['id' => $section->id, 'name' => $section->name, 'class' => $section->schoolClass->name],
            ...$this->routines->forSection($section, $year),
        ]]);
    }

    private function year(): AcademicYear
    {
        $year = request()->integer('academic_year_id')
            ? AcademicYear::findOrFail(request()->integer('academic_year_id'))
            : AcademicYear::current();
        abort_if($year === null, 422, 'There is no current academic year.');

        return $year;
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function personOf($user, string $model)
    {
        return $user->personable_type === (new $model)->getMorphClass() ? $model::find($user->personable_id) : null;
    }
}
