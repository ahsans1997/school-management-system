<?php

namespace App\Http\Controllers;

use App\Actions\Exam\CalculateResults;
use App\Actions\Exam\ResultWorkflow;
use App\Http\Requests\Exam\ResultClassRequest;
use App\Models\Exam;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\Exam\ExamScheduleService;
use App\Services\Exam\ResultService;
use App\Support\PerPage;
use App\Support\TeacherScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * Results (Module 18). Calculated per class from submitted marks
 * (result.process), then draft → review → approved → published
 * (result.publish), every step audited. Reading needs result.view and
 * teachers see only their own sections; /results/me gives students and
 * parents their own published results.
 */
class ResultController extends Controller
{
    public function __construct(
        private readonly ResultService $results,
        private readonly ExamScheduleService $schedules,
    ) {}

    /** Each class sitting the exam: marks progress and results status. */
    public function classes(Exam $exam): JsonResponse
    {
        return response()->json([
            'data' => $this->results->overview(request()->user(), $exam),
            'exam' => $this->schedules->header($exam),
        ]);
    }

    /** Calculate (or recalculate) a class's draft results. */
    public function calculate(ResultClassRequest $request, Exam $exam, CalculateResults $calculate): JsonResponse
    {
        $this->authorizeProcessing($request);
        $calculate->execute($request->user(), $exam, SchoolClass::findOrFail($request->integer('class_id')));

        return $this->classes($exam);
    }

    public function review(ResultClassRequest $request, Exam $exam, ResultWorkflow $flow): JsonResponse
    {
        return $this->step($request, $exam, $flow, 'review');
    }

    public function approve(ResultClassRequest $request, Exam $exam, ResultWorkflow $flow): JsonResponse
    {
        return $this->step($request, $exam, $flow, 'approve');
    }

    public function publish(ResultClassRequest $request, Exam $exam, ResultWorkflow $flow): JsonResponse
    {
        return $this->step($request, $exam, $flow, 'publish');
    }

    public function returnToDraft(ResultClassRequest $request, Exam $exam, ResultWorkflow $flow): JsonResponse
    {
        return $this->step($request, $exam, $flow, 'return');
    }

    public function unpublish(ResultClassRequest $request, Exam $exam, ResultWorkflow $flow): JsonResponse
    {
        return $this->step($request, $exam, $flow, 'unpublish');
    }

    /** The merit list, paginated: ?class_id, ?section_id (orders by section position). */
    public function index(Exam $exam): JsonResponse
    {
        $query = $this->filtered($exam);
        $page = $this->results->meritOrder($query, request()->filled('section_id'))
            ->with(['student', 'enrollment', 'section', 'schoolClass'])
            ->paginate(PerPage::from(request()));

        return response()->json([
            'data' => collect($page->items())->map(fn (Result $r) => $this->results->row($r))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** The tabulation sheet for a class or section, in merit order. */
    public function tabulation(Exam $exam): JsonResponse
    {
        $results = $this->results->meritOrder($this->filtered($exam), request()->filled('section_id'))
            ->with(['details', 'student', 'enrollment', 'section', 'schoolClass'])
            ->get();

        return response()->json(['data' => [...$this->header($exam), ...$this->results->tabulation($results)]]);
    }

    /** Pass rate, GPA, grade spread, per-section and per-subject figures. */
    public function statistics(Exam $exam): JsonResponse
    {
        $results = $this->filtered($exam)->with(['details', 'section'])->get();

        return response()->json(['data' => [...$this->header($exam), ...$this->results->statistics($results)]]);
    }

    /** One student's report card (mark sheet). Teachers: their sections only (404). */
    public function reportCard(Result $result): JsonResponse
    {
        $exam = $result->exam;
        abort_if($exam === null || ! $this->results->visible(request()->user(), $exam)->whereKey($result->id)->exists(), 404);

        return response()->json(['data' => $this->results->reportCard($result)]);
    }

    /** The caller's own published results (student, or a parent's children). */
    public function me(): JsonResponse
    {
        $mine = $this->results->mine(request()->user());

        return response()->json(['data' => [
            'type' => $mine['type'],
            'students' => array_map(fn (array $s) => [
                'student' => $s['student'],
                'results' => $s['results']->map(fn (Result $r) => $this->results->reportCard($r))->all(),
            ], $mine['students']),
        ]]);
    }

    /**
     * Processing applies to a whole class, so it fails closed for
     * section-scoped users: a teacher given result.process or
     * result.publish still can't calculate or move a class's results.
     */
    private function authorizeProcessing(ResultClassRequest $request): void
    {
        abort_if(TeacherScope::applies($request->user()), 403, 'Results are processed by exam admins.');
    }

    /**
     * The exam's results for ?class_id (required) and ?section_id, as the
     * user may see them.
     *
     * @return Builder<Result>
     */
    private function filtered(Exam $exam): Builder
    {
        request()->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')],
            'section_id' => ['nullable', 'integer'],
        ]);

        return $this->results->visible(request()->user(), $exam)
            ->where('class_id', request()->integer('class_id'))
            ->when(request()->integer('section_id'), fn ($q, int $id) => $q->where('section_id', $id));
    }

    /** @return array<string, mixed> */
    private function header(Exam $exam): array
    {
        $class = SchoolClass::findOrFail(request()->integer('class_id'));
        $section = request()->integer('section_id') ? Section::find(request()->integer('section_id')) : null;
        $status = Result::query()->where('exam_id', $exam->id)->where('class_id', $class->id)->value('status');

        return [
            'exam' => $this->schedules->header($exam),
            'class' => ['id' => $class->id, 'name' => $class->name],
            'section' => $section ? ['id' => $section->id, 'name' => $section->name] : null,
            'sections' => $class->sections()->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name'])->values(),
            'status' => $status,
        ];
    }

    private function step(ResultClassRequest $request, Exam $exam, ResultWorkflow $flow, string $action): JsonResponse
    {
        $this->authorizeProcessing($request);
        $flow->execute($request->user(), $exam, SchoolClass::findOrFail($request->integer('class_id')), $action);

        return $this->classes($exam);
    }
}
