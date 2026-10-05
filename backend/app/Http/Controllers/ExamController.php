<?php

namespace App\Http\Controllers;

use App\Actions\Exam\SaveExam;
use App\Enums\ExamType;
use App\Http\Requests\Exam\SaveExamRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\ExamSubjectResource;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\Exam\ExamScheduleService;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Exams (spec §16). Setting one up needs exam.create/edit; deleting archives
 * it (soft delete, restorable). Schedules aren't sensitive, so exam.view
 * sees every class's; GET /exams/me gives each portal its own sections'.
 */
class ExamController extends Controller
{
    public function __construct(private readonly ExamScheduleService $schedules) {}

    /** Paginated; filter by year, type, or archived. Newest first. */
    public function index(): AnonymousResourceCollection
    {
        request()->validate([
            'academic_year_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(ExamType::class)],
            'archived' => ['nullable', 'boolean'],
        ]);

        $exams = Exam::query()
            ->with(['academicYear', 'gradeScale'])
            ->withCount(['subjects as papers_count', 'subjects as classes_count' => fn ($q) => $q->select(DB::raw('count(distinct class_id)'))])
            ->when(request()->boolean('archived'), fn ($q) => $q->onlyTrashed())
            ->when(request()->integer('academic_year_id'), fn ($q, $id) => $q->where('academic_year_id', $id))
            ->when(request()->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->paginate(PerPage::from(request()));

        return ExamResource::collection($exams);
    }

    public function store(SaveExamRequest $request, SaveExam $save): JsonResponse
    {
        return $this->resource($save->execute(null, $request->validated()))->response()->setStatusCode(201);
    }

    /** The exam with the classes taking it and how many papers each sits. */
    public function show(Exam $exam): JsonResponse
    {
        $classes = ExamSubject::query()
            ->where('exam_id', $exam->id)
            ->selectRaw('class_id, count(*) as papers')
            ->groupBy('class_id')
            ->pluck('papers', 'class_id');

        return $this->resource($exam)->additional(['classes' => SchoolClass::query()
            ->whereIn('id', $classes->keys())
            ->orderBy('numeric_level')
            ->get()
            ->map(fn (SchoolClass $c) => ['id' => $c->id, 'name' => $c->name, 'papers' => (int) $classes[$c->id]])
            ->values(),
        ])->response();
    }

    public function update(SaveExamRequest $request, Exam $exam, SaveExam $save): ExamResource
    {
        return $this->resource($save->execute($exam, $request->validated()));
    }

    /** Archive. Marks and results keep pointing at it. */
    public function destroy(Exam $exam): Response
    {
        $exam->delete();

        return response()->noContent();
    }

    public function restore(Exam $exam): ExamResource
    {
        $exam->restore();

        return $this->resource($exam);
    }

    /** The timetable: every paper, or one class's (?class_id) or section's (?section_id). */
    public function schedule(Exam $exam): JsonResponse
    {
        request()->validate([
            'class_id' => ['nullable', 'integer', Rule::exists('classes', 'id')],
            'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')],
        ]);
        $section = request()->integer('section_id') ? Section::find(request()->integer('section_id')) : null;

        return response()->json(['data' => [
            'exam' => $this->schedules->header($exam),
            'papers' => ExamSubjectResource::collection(
                $this->schedules->papers($exam, request()->integer('class_id') ?: null, $section),
            )->resolve(),
        ]]);
    }

    /**
     * The caller's upcoming exams for the current year, by who they are:
     * a teacher's sections, a student's section, a parent's children.
     * Ownership decides, so no exam.* permission.
     */
    public function me(): JsonResponse
    {
        $year = AcademicYear::current();
        abort_if($year === null, 422, 'There is no current academic year.');

        return response()->json(['data' => [
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            ...$this->schedules->mine(request()->user(), $year),
        ]]);
    }

    private function resource(Exam $exam): ExamResource
    {
        return new ExamResource($exam->load(['academicYear', 'gradeScale'])->loadCount([
            'subjects as papers_count',
            'subjects as classes_count' => fn ($q) => $q->select(DB::raw('count(distinct class_id)')),
        ]));
    }
}
