<?php

namespace App\Http\Controllers;

use App\Actions\Exam\SaveExamSubjects;
use App\Http\Requests\Exam\SaveExamSubjectsRequest;
use App\Http\Resources\ExamSubjectResource;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Services\Exam\ExamScheduleService;
use Illuminate\Http\JsonResponse;

/**
 * The papers one class sits in an exam: full/pass marks, the optional
 * CQ/MCQ/practical split, and the date. Read with exam.view, set with
 * exam.edit.
 */
class ExamSubjectController extends Controller
{
    public function __construct(private readonly ExamScheduleService $schedules) {}

    /** The class's papers + its subjects and sections, for the editor. */
    public function show(Exam $exam, SchoolClass $schoolClass): JsonResponse
    {
        return response()->json(['data' => $this->view($exam, $schoolClass)]);
    }

    /** Replace the class's papers (an empty list takes the class out of the exam). */
    public function update(SaveExamSubjectsRequest $request, Exam $exam, SchoolClass $schoolClass, SaveExamSubjects $save): JsonResponse
    {
        $save->execute($exam, $schoolClass, $request->input('subjects'));

        return response()->json(['data' => $this->view($exam->refresh(), $schoolClass)]);
    }

    /** @return array<string, mixed> */
    private function view(Exam $exam, SchoolClass $class): array
    {
        return [
            'exam' => $this->schedules->header($exam),
            'class' => ['id' => $class->id, 'name' => $class->name],
            'sections' => $class->sections()->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name'])->all(),
            'subjects' => ClassSubject::query()
                ->with('subject')
                ->where('class_id', $class->id)
                ->get()
                ->sortBy('subject.name')
                ->map(fn (ClassSubject $cs) => [
                    'class_subject_id' => $cs->id,
                    'subject' => ['id' => $cs->subject->id, 'name' => $cs->subject->name, 'code' => $cs->subject->code, 'type' => $cs->subject->type?->value],
                ])
                ->values()
                ->all(),
            'papers' => ExamSubjectResource::collection($this->schedules->papers($exam, $class->id))->resolve(),
        ];
    }
}
