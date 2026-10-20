<?php

namespace App\Http\Controllers;

use App\Actions\Exam\EnterMarks;
use App\Actions\Exam\SubmitMarks;
use App\Http\Requests\Exam\MarkSheetRequest;
use App\Http\Requests\Exam\SaveMarksRequest;
use App\Http\Requests\Exam\UpdateMarkRequest;
use App\Http\Resources\MarkResource;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\Section;
use App\Services\Exam\ExamScheduleService;
use App\Services\Exam\MarksService;
use App\Support\PerPage;
use App\Support\TeacherScope;
use Illuminate\Http\JsonResponse;

/**
 * Marks entry (Module 17), per sheet = one paper as sat by one section.
 * Two layers on every endpoint (spec §52): exam.marks_entry (route) AND the
 * teacher's own teaching assignment for that subject in that section (403).
 * Marks are internal drafts until submitted; reopening needs exam.edit.
 */
class MarkController extends Controller
{
    public function __construct(
        private readonly MarksService $marks,
        private readonly ExamScheduleService $schedules,
    ) {}

    /** The caller's sheets for the exam with progress, paginated; ?class_id. */
    public function index(Exam $exam): JsonResponse
    {
        request()->validate(['class_id' => ['nullable', 'integer']]);
        $user = request()->user();
        $exam->load('academicYear');

        $page = $this->marks->sheets($user, $exam, request()->integer('class_id') ?: null)->paginate(PerPage::from(request()));
        $papers = collect($page->items());
        $sections = Section::query()->whereKey($papers->map(fn (ExamSubject $p) => $p->getAttribute('sheet_section_id')))->get()->keyBy('id');

        return response()->json([
            'data' => $papers->map(fn (ExamSubject $paper) => $this->marks->sheetRow(
                $paper->setRelation('exam', $exam),
                $sections[$paper->getAttribute('sheet_section_id')],
            ))->values(),
            'exam' => $this->schedules->header($exam),
            // For the class filter: classes the caller has sheets in.
            'classes' => $this->marks->sheets($user, $exam)->toBase()->reorder()
                ->select(['classes.id', 'classes.name', 'classes.numeric_level'])->distinct()
                ->orderBy('classes.numeric_level')->get()
                ->map(fn (object $c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** One sheet: the roll with marks entered so far. */
    public function sheet(MarkSheetRequest $request, Exam $exam): JsonResponse
    {
        [$paper, $section] = $this->resolve($request, $exam);

        return response()->json(['data' => $this->marks->sheet($request->user(), $paper, $section)]);
    }

    /** Save a whole sheet as drafts; returns the sheet. */
    public function store(SaveMarksRequest $request, Exam $exam, EnterMarks $enter): JsonResponse
    {
        [$paper, $section] = $this->resolve($request, $exam);
        $enter->sheet($request->user(), $paper, $section, $request->validated('records'));

        return response()->json(['data' => $this->marks->sheet($request->user(), $paper, $section)]);
    }

    /** Lock the sheet for results once every student has marks. */
    public function submit(MarkSheetRequest $request, Exam $exam, SubmitMarks $submit): JsonResponse
    {
        [$paper, $section] = $this->resolve($request, $exam);
        $submit->submit($request->user(), $paper, $section);

        return response()->json(['data' => $this->marks->sheet($request->user(), $paper, $section)]);
    }

    /** Back to drafts for corrections (exam.edit). */
    public function reopen(MarkSheetRequest $request, Exam $exam, SubmitMarks $submit): JsonResponse
    {
        [$paper, $section] = $this->resolve($request, $exam);
        $submit->reopen($request->user(), $paper, $section);

        return response()->json(['data' => $this->marks->sheet($request->user(), $paper, $section)]);
    }

    /** Change one student's draft marks. */
    public function update(UpdateMarkRequest $request, Mark $mark, EnterMarks $enter): MarkResource
    {
        $paper = $mark->examSubject;
        abort_if($paper->exam === null, 404); // the exam is archived
        $this->marks->authorizeExam($request->user(), $paper->exam);
        TeacherScope::authorizeSubject($request->user(), $mark->enrollment->section, $paper->class_subject_id);

        return new MarkResource($enter->one($request->user(), $mark, $request->validated()));
    }

    /**
     * @return array{0: ExamSubject, 1: Section}
     */
    private function resolve(MarkSheetRequest $request, Exam $exam): array
    {
        return $this->marks->resolve($request->user(), $exam, $request->integer('exam_subject_id'), $request->integer('section_id'));
    }
}
